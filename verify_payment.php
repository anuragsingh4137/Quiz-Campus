<script src="https://khalti.com/static/khalti-checkout.js"></script>
<script>
  var config = {
    publicKey: "<?= KHALTI_PUBLIC_KEY ?>",
    productIdentity: "quizcampus_premium_1",
    productName: "Quiz Campus Premium Plan",
    eventHandler: {
      onSuccess(payload) {
        console.log("Payment success payload:", payload);
        fetch('verify_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            token: payload.token,
            amount: payload.amount
          })
        })
        .then(res => res.json())
        .then(data => {
          console.log("Verify response:", data);
          alert(JSON.stringify(data));
        });
      },
      onError(err) {
        console.error("Khalti error:", err);
        alert("Payment failed or cancelled.");
      },
      onClose() {
        console.log("Khalti widget closed");
      }
    }
  };

  var checkout = new KhaltiCheckout(config);
  document.getElementById("khalti-button").onclick = function () {
    checkout.show({ amount: 10000 }); // Rs 100 in paisa
  }
</script>
