/**
 * @file
 * Global utilities.
 *
 */
(function (Drupal) {
  "use strict";

  Drupal.behaviors.theme_paypal = {
    attach: function (context, settings) {
      // Get the orderId from the URL
      var orderId = new URLSearchParams(window.location.search).get("or_id");
      console.log(orderId);

      // Find the div with class .invoice-totals-container
      var invoiceTotalsContainer = document.querySelector(
        ".invoice-totals-container"
      );

      // Check if the element is found and if the link hasn't been appended yet
      if (
        invoiceTotalsContainer &&
        !invoiceTotalsContainer.dataset.linkAppended
      ) {
        // Create a new 'a' element
        var payNowLink = document.createElement("a");
        // Add a class to the 'a' element
        payNowLink.classList.add("btn");
        payNowLink.classList.add("btn-primary");
        payNowLink.classList.add("paynow_button");

        // Set the href attribute with the orderId
        payNowLink.href = "/checkout/" + orderId + "/review";

        // Set the text content
        payNowLink.textContent = "Pay Now";

        // Append the 'a' element after the .invoice-totals-container div
        invoiceTotalsContainer.insertAdjacentElement("afterend", payNowLink);

        // Mark the container to indicate that the link has been appended
        invoiceTotalsContainer.dataset.linkAppended = true;
      } else {
        console.error(
          "Element with class .invoice-totals-container not found or link already appended."
        );
      }
    },
  };
})(Drupal);
