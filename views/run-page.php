<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

$run_nonce = wp_create_nonce( 'wp2static-run-page' );
?>

<script type="text/javascript">
var latest_log_row = 0;

jQuery(document).ready(function($){
    var run_data = {
        action: 'wp2static_run',
        security: '<?php echo $run_nonce; ?>',
    };

    var log_data = {
        dataType: 'text',
        action: 'wp2static_poll_log',
        startRow: latest_log_row,
        security: '<?php echo $run_nonce; ?>',
    };

    function responseErrorHandler( jqXHR, textStatus, errorThrown ) {
        $("#wp2static-spinner").removeClass("is-active");
        $("#wp2static-run" ).prop('disabled', false);

        console.log(errorThrown);
        console.log(jqXHR.responseText);

        alert(`${jqXHR.status} error code returned from server.
Please check your server's error logs or try increasing your max_execution_time limit in PHP if this consistently fails after the same duration.
More information of the error may be logged in your browser's console.`);
    }

    function pollLogs() {
        $.post(ajaxurl, log_data, function(response) {
            $('#wp2static-run-log').val(response);
            $("#wp2static-poll-logs" ).prop('disabled', false);
        });
    }

    $( "#wp2static-run" ).click(function() {
        $("#wp2static-spinner").addClass("is-active");
        $("#wp2static-run" ).prop('disabled', true);

        var stage = $("#wp2static-stage").val();
        if (stage === 'null') {
            delete run_data['stage'];
        }else{
            run_data['stage'] = parseInt(stage,10);
        }

        var offset = $("#wp2static-offset").val();
        var limit = $("#wp2static-limit").val();
        if (offset !== '' && limit !== ''){
            run_data['offset'] = parseInt(offset,10);
            run_data['limit'] = parseInt(limit,10);
        }else{
            delete run_data['offset'];
            delete run_data['limit'];
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: run_data,
            timeout: 0,
            success: function() {
                $("#wp2static-spinner").removeClass("is-active");
                $("#wp2static-run" ).prop('disabled', false);
                pollLogs();
            },
            error: responseErrorHandler
        });

    });

    $( "#wp2static-poll-logs" ).click(function() {
        $("#wp2static-poll-logs" ).prop('disabled', true);
        pollLogs();
    });
});
</script>

<div class="wrap">
    <br>

    <select id="wp2static-stage">
        <option value="null">All stages</option>
        <option value="1">URL detection</option>
        <option value="2">Crawl</option>
        <option value="3">Post process</option>
        <option value="4">Deploy</option>
        <option value="4">Post deploy action</option>
    </select>
    <input type="text" placeholder="offset" id="wp2static-offset">
    <input type="text" placeholder="limit" id="wp2static-limit">
    <button class="button button-primary" id="wp2static-run">Generate static site</button>

    <div id="wp2static-spinner" class="spinner" style="padding:2px;float:none;"></div>

    <br>
    <br>

    <button class="button" id="wp2static-poll-logs">Refresh logs</button>
    <br>
    <br>
    <textarea id="wp2static-run-log" rows=30 style="width:99%;">
    Logs will appear here on completion or click "Refresh logs" to check progress
    </textarea>
</div>
