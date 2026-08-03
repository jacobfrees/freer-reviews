/* FreeFlow Reviews - front-end interactions */
(function ($) {
  'use strict';

  function setFilled($group, val) {
    $group.find('.ffr-star').each(function () {
      $(this).toggleClass('ffr-star--filled', parseInt($(this).data('value'), 10) <= val);
    });
  }

  // Hover preview.
  $(document).on('mouseenter', '.ffr-stars--input .ffr-star', function () {
    var $group = $(this).closest('.ffr-stars--input');
    var hover = parseInt($(this).data('value'), 10);
    $group.data('hover', hover);
    setFilled($group, hover);
  });

  // Leave: restore the selected rating.
  $(document).on('mouseleave', '.ffr-stars--input', function () {
    var $group = $(this);
    var val = parseInt($group.find('input').val(), 10) || 0;
    setFilled($group, val);
  });

  // Click to select.
  $(document).on('click', '.ffr-stars--input .ffr-star', function (e) {
    e.preventDefault();
    var $group = $(this).closest('.ffr-stars--input');
    var val = parseInt($(this).data('value'), 10);
    $group.find('input').val(val);
    setFilled($group, val);
  });

  // AJAX submit.
  $(document).on('submit', '.ffr-form form', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $msg = $form.find('.ffr-message').removeClass('is-error is-ok').text('');
    var rating = $form.find('input[name="ffr_rating"]').val();
    var name = $form.find('input[name="ffr_name"]').val().trim();
    var text = $form.find('textarea[name="ffr_text"]').val().trim();

    if (!rating || parseInt(rating, 10) < 1 || name === '' || text.length < 10) {
      $msg.addClass('is-error').text(FFR.msg.required);
      return;
    }

    $form.find('.ffr-submit').prop('disabled', true);

    $.post(FFR.ajax_url, $form.serialize() + '&action=ffr_submit_review', function (res) {
      if (res && res.success) {
        $msg.addClass('is-ok').text(res.data.message);
        $form.find('input,textarea').val('');
        $form.find('.ffr-star').removeClass('ffr-star--filled');
      } else {
        $msg.addClass('is-error').text((res && res.data && res.data.message) || FFR.msg.error);
        $form.find('.ffr-submit').prop('disabled', false);
      }
    }).fail(function () {
      $msg.addClass('is-error').text(FFR.msg.error);
      $form.find('.ffr-submit').prop('disabled', false);
    });
  });
})(jQuery);
