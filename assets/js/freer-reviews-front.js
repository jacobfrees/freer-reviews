/* Freer Reviews - front-end interactions */
(function ($) {
  'use strict';

  function setFilled($group, val) {
    $group.find('.freer-reviews-star').each(function () {
      $(this).toggleClass('freer-reviews-star--filled', parseInt($(this).data('value'), 10) <= val);
    });
  }

  // Hover preview.
  $(document).on('mouseenter', '.freer-reviews-stars--input .freer-reviews-star', function () {
    var $group = $(this).closest('.freer-reviews-stars--input');
    var hover = parseInt($(this).data('value'), 10);
    $group.data('hover', hover);
    setFilled($group, hover);
  });

  // Leave: restore the selected rating.
  $(document).on('mouseleave', '.freer-reviews-stars--input', function () {
    var $group = $(this);
    var val = parseInt($group.find('input').val(), 10) || 0;
    setFilled($group, val);
  });

  // Click to select.
  $(document).on('click', '.freer-reviews-stars--input .freer-reviews-star', function (e) {
    e.preventDefault();
    var $group = $(this).closest('.freer-reviews-stars--input');
    var val = parseInt($(this).data('value'), 10);
    $group.find('input').val(val);
    setFilled($group, val);
  });

  // Parse the config baked into the form container by the PHP shortcode.
  function fieldConfig($form) {
    var $box = $form.closest('.freer-reviews-form');
    var fields = ($box.data('fields') || 'rating,name,email,text').split(',');
    var require = ($box.data('require') || '').split(',').filter(Boolean);
    var min = parseInt($box.data('min'), 10) || 0;
    return { fields: fields, require: require, min: min };
  }

  // Built-in single-click "I'm not a robot" button.
  $(document).on('click', '.freer-reviews-human-btn', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $cap = $btn.closest('.freer-reviews-captcha--click');
    if ($btn.hasClass('is-verified')) { return; } // already done
    $btn.addClass('is-checking').prop('disabled', true);
    var data = {
      action: $cap.data('action'),
      freer_reviews_group: $cap.data('group'),
      freer_reviews_human_nonce: $cap.find('input[name="freer_reviews_human_nonce"]').val()
    };
    $.post(FREER_REVIEWS.ajax_url, data, function (res) {
      if (res && res.success) {
        $cap.find('.freer-reviews-human-token').val(res.data.token);
        $btn.removeClass('is-checking').addClass('is-verified')
           .attr('aria-pressed', 'true').prop('disabled', false);
      } else {
        $btn.removeClass('is-checking').prop('disabled', false);
      }
    }).fail(function () {
      $btn.removeClass('is-checking').prop('disabled', false);
    });
  });

  // Is the human verification satisfied for this form? (Turnstile or built-in)
  function captchaDone($form) {
    var $cap = $form.find('.freer-reviews-captcha');
    if (!$cap.length) { return true; } // captcha disabled on this form
    var token = $cap.find('input[type="hidden"]').val() || $cap.find('.freer-reviews-human-token').val();
    return !!token;
  }

  function fieldValid($form, cfg, field) {
    var v;
    if (field === 'rating') {
      v = parseInt($form.find('input[name="freer_reviews_rating"]').val(), 10) || 0;
      return v >= 1;
    }
    if (field === 'name') {
      v = $form.find('input[name="freer_reviews_name"]').val().trim();
      return v !== '';
    }
    if (field === 'email') {
      v = $form.find('input[name="freer_reviews_email"]').val().trim();
      if (v === '') { return true; } // optional email may be blank
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    }
    if (field === 'text') {
      v = $form.find('textarea[name="freer_reviews_text"]').val().trim();
      if (cfg.min > 0) { return v.length >= cfg.min; }
      return v !== '';
    }
    return true;
  }

  // AJAX submit — client check only enforces fields the PHP marked as REQUIRED.
  $(document).on('submit', '.freer-reviews-form form', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $msg = $form.find('.freer-reviews-message').removeClass('is-error is-ok').text('');
    var cfg = fieldConfig($form);

    var missing = [];
    for (var i = 0; i < cfg.require.length; i++) {
      if (!fieldValid($form, cfg, cfg.require[i])) { missing.push(cfg.require[i]); }
    }
    if (missing.length) {
      $msg.addClass('is-error').text(FREER_REVIEWS.msg.required);
      return;
    }
    if (!captchaDone($form)) {
      $msg.addClass('is-error').text('Please complete the verification.');
      return;
    }

    $form.find('.freer-reviews-submit').prop('disabled', true);

    $.post(FREER_REVIEWS.ajax_url, $form.serialize() + '&action=freer_reviews_submit_review', function (res) {
      if (res && res.success) {
        $msg.addClass('is-ok').text(res.data.message);
        $form.find('input,textarea').val('');
        $form.find('.freer-reviews-star').removeClass('freer-reviews-star--filled');
      } else {
        $msg.addClass('is-error').text((res && res.data && res.data.message) || FREER_REVIEWS.msg.error);
        $form.find('.freer-reviews-submit').prop('disabled', false);
      }
    }).fail(function () {
      $msg.addClass('is-error').text(FREER_REVIEWS.msg.error);
      $form.find('.freer-reviews-submit').prop('disabled', false);
    });
  });
})(jQuery);
