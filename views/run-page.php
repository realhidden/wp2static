<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

$run_nonce = wp_create_nonce( 'wp2static-run-page' );
?>

<script type="text/javascript">
jQuery(document).ready(function($){
    function responseErrorHandler( jqXHR, textStatus, errorThrown ) {
        $("#wp2static-spinner").removeClass("is-active");
        $("#wp2static-run" ).prop('disabled', false);

        console.log(errorThrown);
        console.log(jqXHR.responseText);

        alert(`${jqXHR.status} error code returned from server.
Please check your server's error logs or try increasing your max_execution_time limit in PHP if this consistently fails after the same duration.
More information of the error may be logged in your browser's console.`);
    }

    $( "#wp2static-run" ).click(function() {
        $("#wp2static-spinner").addClass("is-active");
        $("#wp2static-run" ).prop('disabled', true);

        var run_data = {
            action: 'wp2static_run',
            security: '<?php echo $run_nonce; ?>',
        };

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
            },
            error: responseErrorHandler
        });

    });

    $( "#wp2static-poll-logs" ).click(function() {
        $("#wp2static-poll-logs" ).prop('disabled', true);
        $.post(ajaxurl, {
            dataType: 'text',
            action: 'wp2static_poll_log',
            startRow: 0,
            security: '<?php echo $run_nonce; ?>',
        }, function(response) {
            $('#wp2static-run-log').val(response);
            $("#wp2static-poll-logs" ).prop('disabled', false);
        });
    });

    $( "#wp2static-clear-logs" ).click(function() {
        $("#wp2static-clear-logs" ).prop('disabled', true);
        $.post(ajaxurl, {
            dataType: 'text',
            action: 'wp2static_clear_log',
            security: '<?php echo $run_nonce; ?>',
        }, function(response) {
            $('#wp2static-run-log').val(response);
            $("#wp2static-clear-logs" ).prop('disabled', false);
        });
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
        <option value="5">Post deploy action</option>
    </select>
    <input type="text" placeholder="offset" id="wp2static-offset">
    <input type="text" placeholder="limit" id="wp2static-limit">
    <button class="button button-primary" id="wp2static-run">Generate static site</button>

    <div id="wp2static-spinner" class="spinner" style="padding:2px;float:none;"></div>

    <br>
    <br>

    <button class="button" id="wp2static-poll-logs">Refresh logs</button>
    <button class="button" id="wp2static-clear-logs">Clear logs</button>
    <br>
    <br>
    <textarea id="wp2static-run-log" rows=30 style="width:99%;">
    Logs will appear here on completion or click "Refresh logs" to check progress
    </textarea>
</div>
