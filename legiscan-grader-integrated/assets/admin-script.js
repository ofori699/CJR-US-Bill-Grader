jQuery(document).ready(function($) {
    // Confirm before filtering bills
    $('input[name="filter_bills"]').click(function(e) {
        if (!confirm('This will permanently delete bills that do not match your keywords. Continue?')) {
            e.preventDefault();
        }
    });

    // Auto-save API keys on blur
    $('#legiscan_api_key, #census_api_key').blur(function() {
        var $this = $(this);
        if ($this.val().length > 10) { // Basic validation
            $this.css('border-color', 'green');
        } else {
            $this.css('border-color', 'red');
        }
    });

    // Progress bar animation
    $('.legiscan-progress-fill').each(function() {
        var width = $(this).css('width');
        $(this).css('width', '0').animate({width: width}, 1000);
    });
});