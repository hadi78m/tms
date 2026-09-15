$(function () {
    $(".main_form").on("submit", function (e) {
        e.stopImmediatePropagation();
        e.preventDefault();
        $(".error_message").text(""); //.removeClass();
        $("#alert").hide();
        $("#success").hide();

        var submit = "";
        submit = $(".submit").text();

        if (submit == "") {
            submit = $("#submit").text();
        }
        $(".submit").text("در حال ارسال...").prop("disabled", true);
        $("#submit").text("در حال ارسال...").prop("disabled", true);

        // return;
        $.ajax({
            url: $(this).attr("action"),
            method: $(this).attr("method"),
            data: new FormData(this),
            processData: false,
            dataType: "json",
            contentType: false,
            beforeSend: function () {
                $(document).find("span.error-text").text("");
            },
            success: function (response) {
                $(".submit").text(submit).prop("disabled", false);
                $("#submit").text(submit).prop("disabled", false);

                if (response.status == false) {

                    // $(".error_message").text("");
                    if (response.errors) {
                        $.each(response.errors, function (prefix, val) {
                            // $("#alert").show();
                            // $(".error_message").text(val[0]);
                            // $(".error_message").text(response.message);
                            showError('',val[0])
                        });
                    } 
                     if (response.url) {
                        window.location.replace(response.url);
                    }
                    if(!response.errors){
                        showError('', response.message);
                    }

                    setTimeout(() => {
                        $(".close_btn").trigger("click");
                    }, 5000);
                }
                if (response.status == true) {
                    $(".main_form")[0].reset();
                    $("select").val("").trigger("change");

                    // نمایش پیام موفقیت
                    showSystemSuccess('', response.message);

                    $("body #table").DataTable().ajax.reload();
                    $("body .table").DataTable().ajax.reload();

                    $("#preview-container").empty();

                    setTimeout(() => {
                        editor.setData("");
                        editor1.setData("");
                        editorid.setData("");
                    }, 1000);
                }
            },
        });
    });

    $(".close_btn").on("click", function (e) {
        $(".error_message").text("");
        $("#alert").hide();
        $("#success").hide();
    });
});

