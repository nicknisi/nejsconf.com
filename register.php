---
layout: skinny
---

{% raw %}
<?php
  define('VALID_ENTRY_POINT', true);
  require 'vendor/autoload.php';
  require 'util.php';
  $config = require 'config.php';

  \Stripe\Stripe::setApiKey($config['stripe']['secret_key']);

  $ticket_price = $config['checkout']['ticket_price'];
  $number_of_tickets = 1;
  $coupon_code = '';
  $form_errors = array();
  $attendee_data = array();
  $stripe_error = false;
  $receipt_email = '';

  if($_POST) {
    $number_of_tickets = min(intval($_POST['number_of_tickets']), $config['checkout']['max_tickets']);

    // Validate Coupon Code
    if(($coupon_code = arr_get($_POST, 'coupon_code')) != null) {
      $coupon_price = arr_get($config['checkout']['coupons'], $coupon_code);
      if(null == $coupon_price) {
        $coupon_code = null;
      }
      else {
        $ticket_price = $coupon_price;
      }
    }

    // Attendee validation
    for($i = 1; $i <= $number_of_tickets; $i++) {
      $attendee = array(
        'first_name' => trim(arr_get($_POST, 'first_name_' . $i, '')),
        'last_name'  => trim(arr_get($_POST, 'last_name_' . $i, '')),
        'email'      => trim(arr_get($_POST, 'email_' . $i, '')),
        'twitter'    => trim(arr_get($_POST, 'twitter_' . $i, '')),
        'company'    => trim(arr_get($_POST, 'company_' . $i, '')),
        'title'      => trim(arr_get($_POST, 'title_' . $i, '')),
      );
      $attendee_data[$i] = $attendee;

      $errors = array();

      if(empty($attendee['first_name'])) {
        $errors['first_name'] = 'This field is required.';
      }
      if(empty($attendee['last_name'])) {
        $errors['last_name'] = 'This field is required.';
      }
      if(! (filter_var($attendee['email'], FILTER_VALIDATE_EMAIL) && preg_match('/@.+\./', $attendee['email'])) ) {
        $errors['email'] = 'An email is required.';
      }

      if(0 != count($errors)) {
        $form_errors[$i] = $errors;
      }
    }

    $receipt_email = trim(arr_get($_POST, 'receipt_email', ''));
    if(! (filter_var($receipt_email, FILTER_VALIDATE_EMAIL) && preg_match('/@.+\./', $receipt_email)) ) {
      $form_errors['receipt_email'] = 'An email is required.';
    }


    if(0 == count($form_errors)) {
      $stripe_token = $_POST['stripeToken'];

      try {
        $charge = \Stripe\Charge::create(array(
          "amount"        => $ticket_price * $number_of_tickets * 100,  // it's in pennies
          "currency"      => "usd",
          "source"        => $stripe_token,
          "description"   => "$number_of_tickets NEJSCONF 2015 Tickets",
          "receipt_email" => $receipt_email,
          "metadata"      => array(
            'attendees'         => json_encode($attendee_data),
            'coupon_code'       => $coupon_code,
            'ticket_price'      => $ticket_price,
            'number_of_tickets' => $number_of_tickets,
          ),
        ));
      }
      catch(\Stripe\Error\Card $e) {
        $error_json = $e->getJsonBody();
        $stripe_error = $error_json['error']['message'];
      }
      catch(\Stripe\Error $e) {
        $stripe_error = "An error occurred charging your card. Please try again.";
      }
    }

  }

?>
<form method="POST" id="register_form">

  <label for="number_of_tickets">Number Of Tickets</label>
  <select id="number_of_tickets" name="number_of_tickets">
    <?php for($i = 1; $i <= $config['checkout']['max_tickets']; $i++): ?>
    <option<?php if($i == $number_of_tickets):?> selected="selected"<?php endif; ?>><?php echo $i; ?></option>
    <?php endfor; ?>
  </select>

  <div id="ticket_blocks">
  <?php for($i = 1; $i <= $number_of_tickets; $i++): ?>
    <fieldset id="ticket_block_<?php echo $i; ?>">
      <legend>Attendee #<?php echo $i; ?></legend>

      <div<?php if(arr_get(arr_get($form_errors, $i, array()), 'first_name')): ?> class="error"<?php endif; ?>>
        <label for="first_name_<?php echo $i; ?>">First Name <span class="required">(required)</span></label>
        <input name="first_name_<?php echo $i; ?>" data-validate="required" type="text" value="<?php echo htmlspecialchars(arr_get($_POST, "first_name_" . $i)); ?>" />
        <div class="form_error" id="error_first_name_<?php echo $i; ?>"><?php echo arr_get(arr_get($form_errors, $i, array()), 'first_name'); ?></div>
      </div>

      <div<?php if(arr_get(arr_get($form_errors, $i, array()), 'last_name')): ?> class="error"<?php endif; ?>>
        <label for="last_name_<?php echo $i; ?>">Last Name <span class="required">(required)</span></label>
        <input name="last_name_<?php echo $i; ?>" data-validate="required" type="text" value="<?php echo htmlspecialchars(arr_get($_POST, "last_name_" . $i)); ?>" />
        <div class="form_error" id="error_last_name_<?php echo $i; ?>"><?php echo arr_get(arr_get($form_errors, $i, array()), 'last_name'); ?></div>
      </div>

      <div<?php if(arr_get(arr_get($form_errors, $i, array()), 'email')): ?> class="error"<?php endif; ?>>
        <label for="email_<?php echo $i; ?>">Email Address <span class="required">(required)</span></label>
        <input name="email_<?php echo $i; ?>" data-validate="email" type="text" value="<?php echo arr_get($_POST, "email_" . $i); ?>" />
        <div class="form_error" id="error_email_<?php echo $i; ?>"><?php echo arr_get(arr_get($form_errors, $i, array()), 'email'); ?></div>
      </div>

      <div>
        <label for="twitter_<?php echo $i; ?>">Twitter Username</label>
        <input name="twitter_<?php echo $i; ?>" type="text" value="<?php echo htmlspecialchars(arr_get($_POST, "twitter_" . $i)); ?>" />
      </div>

      <div>
        <label for="company_<?php echo $i; ?>">Company</label>
        <input name="company_<?php echo $i; ?>" type="text" value="<?php echo htmlspecialchars(arr_get($_POST, "company_" . $i)); ?>" />
      </div>

      <div>
        <label for="title_<?php echo $i; ?>">Job Title</label>
        <input name="title_<?php echo $i; ?>" type="text" value="<?php echo htmlspecialchars(arr_get($_POST, "title_" . $i)); ?>" />
      </div>
    </fieldset>
  <?php endfor; ?>
  </div>

  <fieldset>
    <legend>Coupon Code</legend>
    <input type="text" name="coupon_code" id="coupon_code" value="<?php echo $coupon_code; ?>" /> <a href="#" id="update_coupon">Apply Code</a>
  </fieldset>
  <h3>Total: $<span id="current_price"><?php echo $ticket_price; ?></span> &times; <?php echo $number_of_tickets; ?> = <span id="ticket_total">$<?php echo $ticket_price * $number_of_tickets; ?></span></h3>

  <fieldset>
    <legend>Payment</legend>

    <div class="payment-errors"><?php if($stripe_error) { echo htmlspecialchars($stripe_error); } ?></div>
    
    <div<?php if(arr_get($form_errors, 'receipt_email', false)): ?> class="error"<?php endif; ?>>
      <label>
        Receipt Email Address
        <span class="required">(required)</span>
      </label>
      <input type="text" data-validate="email" name="receipt_email" value="<?php echo htmlspecialchars($receipt_email); ?>"/>
      <div class="form_error"><?php echo arr_get($form_errors, 'receipt_email', ''); ?></div>
    </div>

    <div>
      <label>
        Card Number
        <span class="required">(required)</span>
      </label>
      <input type="text" size="20" data-stripe="number" data-validate="required" />
      <div class="form_error"></div>
    </div>

    <div>
      <label>
        CVC
        <span class="required">(required)</span>
      </label>
      <input type="text" size="4" data-stripe="cvc" data-validate="required"/>
      <div class="form_error"></div>
    </div>

    <div>
      <label>Expiration (MM/YYYY) <span class="required">(required)</span></label>
      <input type="text" class="short" size="2" data-stripe="exp-month" data-validate="required"/> / 
      <input type="text" class="short" size="4" data-stripe="exp-year" data-validate="required"/>
      <div class="form_error"></div>
    </div>

  </fieldset>

  <button type="submit">Complete Registration</button>

  <div style="margin-top: 20px;">
    <img src="/assets/img/stripe.png" alt="Powered By Stripe" style="width: 100px;" />
  </div>

</form>

<script type="text/html" id="ticket_block_template">
<legend>Attendee #{{block_number}}</legend>
<div>
  <label for="first_name_{{block_number}}">First Name <span class="required">(required)</span></label>
  <input name="first_name_{{block_number}}" data-validate="required" type="text" />
  <div class="form_error" id="error_first_name_{{block_number}}"></div>
</div>

<div>
  <label for="last_name_{{block_number}}">Last Name <span class="required">(required)</span></label>
  <input name="last_name_{{block_number}}" data-validate="required" type="text" />
  <div class="form_error" id="error_last_name_{{block_number}}"></div>
</div>

<div>
  <label for="email_{{block_number}}">Email Address <span class="required">(required)</span></label>
  <input name="email_{{block_number}}" data-validate="email" type="text" />
  <div class="form_error" id="error_email_{{block_number}}"></div>
</div>

<div>
  <label for="twitter{{block_number}}">Twitter Username</label>
  <input name="twitter{{block_number}}" type="text" />
</div>

<div>
  <label for="company_{{block_number}}">Company</label>
  <input name="company_{{block_number}}" type="text" />
</div>

<div>
  <label for="title_{{block_number}}">Job Title</label>
  <input name="title_{{block_number}}" type="text" />
</div>
</script>

<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/zepto/1.1.4/zepto.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/validator/3.12.0/validator.min.js"></script>
<script>

  Stripe.setPublishableKey('<?php echo $config['stripe']['public_key']; ?>');

  $(function () {

    var original_ticket_price = <?php echo $config['checkout']['ticket_price']; ?>,
         current_ticket_price = <?php echo $ticket_price; ?>,
               $ticket_select = document.getElementById("number_of_tickets"),
        $ticket_block_wrapper = document.getElementById('ticket_blocks'),
        ticket_block_template = document.getElementById('ticket_block_template').innerText,
                $ticket_total = document.getElementById('ticket_total'),
               $current_price = document.getElementById('current_price'),
                 $coupon_code = document.getElementById('coupon_code');

    $ticket_select.onchange = updateForm;

    document.getElementById('update_coupon').onclick = function (e) {
      e.preventDefault();
      $.getJSON("/coupon.php", {'coupon_code': $coupon_code.value}, function (data) {
        if(data['code'] === false) {
          $coupon_code.value = '';
          alert("Sorry, that coupon code does not exist.");
        }
        current_ticket_price = data['price'];
        updatePrice();
      });
    };

    $("#register_form").on('submit', function () {
      
      var errors = false,
           $form = $(this);

      $form.find('input').each(function (i, e) {
        
        var $input = $(e),
          $wrapper = $input.parent(),
     $errorMessage = $wrapper.find(".form_error"),
         validates = $input.data('validate'),
             value = e.value.replace(/^\s+|\s+$/g, '');

        $wrapper.removeClass("error");
        $errorMessage.text('');

        if( validates === 'required') {
          if(! validator.isLength(value, 1)) {
            $wrapper.addClass('error');
            $errorMessage.text('This field is required.');
            errors = true;
          }
        }
        else if ( validates === 'email' ) {
          if(! validator.isEmail(value)) {
            $wrapper.addClass('error');
            $errorMessage.text('An email is required.');
            errors = true;
          }
        }
      });

      if( ! errors ) {
        Stripe.card.createToken(this, stripeResponseHandler);
        $form.find('button').prop('disabled', true);
      }

      return false;
    });

    function stripeResponseHandler(status, response) {
      var $form = $('#register_form');

      if (response.error) {
        // Show the errors on the form
        $form.find('.payment-errors').text(response.error.message);
        $form.find('button').prop('disabled', false);
      }
      else {
        var token = response.id;
        $form.append($('<input type="hidden" name="stripeToken" />').val(token));
        $form.get(0).submit();
      }
    };

    function updateForm () {
        var i, block, ticket_blocks = parseInt($ticket_select.value, 10);
        for(i = 1; i <= <?php echo $config['checkout']['max_tickets']; ?>; i++) {
          block = document.getElementById("ticket_block_" + i);
          // Delete old blocks
          if(i > ticket_blocks) { 
            if(null !== block) {
              block.parentNode.removeChild(block);
            }
          }
          // Inject new blocks
          else {
            if(null === block) {
              var ticketBlock = document.createElement("fieldset");
              ticketBlock.innerHTML = ticket_block_template.replace(/{{block_number}}/g, i);
              ticketBlock.id = "ticket_block_" + i;
              $ticket_block_wrapper.appendChild(ticketBlock);
            }
          }
        }

        updatePrice();
      }

    function updatePrice () {
      $current_price.innerText = current_ticket_price;
      $ticket_total.innerText = "$" + (current_ticket_price * parseInt($ticket_select.value, 10));
    }
  });
</script>
{% endraw %}
