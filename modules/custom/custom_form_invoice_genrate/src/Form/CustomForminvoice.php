<?php

namespace Drupal\custom_form_invoice_genrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_checkout\Entity\Checkout;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use ReflectionObject;
use Drupal\Core\Datetime\DrupalDateTime;
use ApplicationSubmited;
use Drupal\commerce_invoice\InvoiceGeneratorInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\user\Entity\User;



class CustomForminvoice extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_form_invoice_genrate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load all authenticated users.
    $users = User::loadMultiple();
    $authenticated_users = [];

    foreach ($users as $user) {
      // Check if the user is authenticated.
      if ($user->isAuthenticated()) {
        $authenticated_users[$user->id()] = $user->getAccountName();
      }
    }

    $form['selected_user'] = [
      '#type' => 'select',
      '#title' => $this->t('Select User'),
      '#options' => $authenticated_users,
      '#required' => TRUE,
    ];

    $form['items'] = [
      '#type' => 'fieldset',
      '#title' => $this->t(''),
      '#tree' => TRUE,
    ];
  
    $form['items']['item_list'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'item-list-wrapper'],
    ];
  
    // Check if there are already items in the form state
    $num_items = $form_state->get('num_items') ?: 1;
  
    for ($i = 0; $i < $num_items; $i++) {
      $form['items']['item_list'][$i]['item_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('URL'),
        '#required' => TRUE,
      ];
  
      $form['items']['item_list'][$i]['item_price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Price'),
        '#required' => TRUE,
      ];
    }
  
    $form['items']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add More'),
      '#submit' => ['::addMoreSubmit'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'item-list-wrapper',
      ],
    ];
  
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Genrate Invoice'),
    ];
  
    return $form;
  }
  
  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $num_items = $form_state->get('num_items') ?: 1;
    $form_state->set('num_items', $num_items + 1);
    $form_state->setRebuild(TRUE);
  }
  
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['items']['item_list'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate price fields to allow only numeric values.
    $items = $form_state->getValue('items')['item_list'];
    foreach ($items as $item) {
      if (!is_numeric($item['item_price'])) {
        $form_state->setErrorByName('item_list', $this->t('Please enter a numeric value for the price.'));
        return;
      }
    }
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Extract submitted values.
    $selected_user_id = $form_state->getValue('selected_user');
    $selected_user = User::load($selected_user_id);
    $selected_uid = $selected_user->id();
    $items = $form_state->getValue('items')['item_list'];
  
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    $orderStorage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $billingProfileStorage = \Drupal::entityTypeManager()->getStorage('profile');
  
    // Get the commerce_invoice.invoice_generator service.
    $invoiceGenerator = \Drupal::service('commerce_invoice.invoice_generator');
  
    // Get or create the cart.
    $cart = $cartProvider->getCart('default');
    if (!$cart) {
      $cart = $cartProvider->createCart('default');
    }
  
    // Check if the cart is not empty.
    if ($cart && count($cart->getItems()) > 0) {
      // Remove all items from the cart.
      $orderItemStorage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
      $orderItemStorage->delete($cart->getItems());
    }
  
    foreach ($items as $item) {
      // Create a Price object.
      $unit_price = new Price($item['item_price'], 'USD');
  
      $orderItem = OrderItem::create([
        'type' => 'invoice_payment', // Replace with your order item type, if applicable.
      ]);
      $orderItem->setTitle($item['item_label']);
      $orderItem->setUnitPrice($unit_price);
      $orderItem->save();
  
      // Add the order item to the cart.
      $cart->addItem($orderItem);
    }
  
    // Save the cart.
    $cart->save();
  
    // Create an order from the cart.
    $order_type_id = 'custom_invoice'; // Replace with your order type ID.
    $order_type = OrderType::load($order_type_id);
    $order = Order::create([
      // 'type' => $order_type_id,
      'type' => 'custom_invoice',
      'store_id' => $cart->getStoreId(),
      'state' => 'draft',
      // 'uid' => $cart->getCustomerId(),
      'uid' => $selected_uid,
      'order_items' => $cart->getItems(),
    ]);
    $order->save();

    // Get the store from the order.
    $store = $order->getStore();
  
    // Create a billing profile (you may need to adjust this based on your setup).
    $billing_profile = $billingProfileStorage->create([
      'type' => 'customer_profile',
      'uid' => $selected_uid,
      // 'uid' => $cart->getCustomerId(),
      // Add relevant address information.
      // ...
    ]);
    $billing_profile->save();
    
    // Generate an invoice for the order.
    $invoiceGenerator->generate([$order], $store);
    
    // Redirect to the payment page.
    // $form_state->setRedirect('commerce_checkout.form', ['commerce_order' => $order->id()]);
  
    // // Optionally, redirect to the cart, another page, or the invoice page.
    // $form_state->setRedirect('commerce_cart.page');
     // Set a status message after successful submission.
      // Set a status message after successful submission.
    $this->messenger()->addMessage($this->t('Invoice generated successfully for user @username.', [
      '@username' => $selected_user->getAccountName(),
    ]));
  }
  
}
