// LegiScan Grader Plugin JavaScript
(function($) {
    'use strict';

    // Initialize plugin functionality
    $(document).ready(function() {

        // API call tracking
        function trackApiCall() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'track_api_call',
                    nonce: legiscan_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('API call tracked:', response.data.count);
                    }
                }
            });
        }

        // Expose trackApiCall globally for other scripts to use
        window.legiscanTrackApiCall = trackApiCall;

        // Handle dataset upload
        $('#legiscan-upload-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.append('action', 'upload_dataset');
            formData.append('nonce', legiscan_ajax.nonce);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#upload-progress').show();
                },
                success: function(response) {
                    $('#upload-progress').hide();
                    if (response.success) {
                        alert('Dataset uploaded successfully!');
                        location.reload();
                    } else {
                        alert('Error uploading dataset: ' + response.data);
                    }
                },
                error: function() {
                    $('#upload-progress').hide();
                    alert('Error uploading dataset.');
                }
            });
        });

        // Handle bill filtering
        $('#legiscan-filter-form').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();
            formData += '&action=filter_bills&nonce=' + legiscan_ajax.nonce;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    $('#filter-progress').show();
                },
                success: function(response) {
                    $('#filter-progress').hide();
                    if (response.success) {
                        alert('Bills filtered successfully!');
                        location.reload();
                    } else {
                        alert('Error filtering bills: ' + response.data);
                    }
                },
                error: function() {
                    $('#filter-progress').hide();
                    alert('Error filtering bills.');
                }
            });
        });
    });

})(jQuery);
