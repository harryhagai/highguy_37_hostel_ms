(function () {
    var input = document.getElementById("profilePhotoInput");
    var form = document.getElementById("photoForm");

    if (!input || !form) return;

    input.addEventListener("change", function () {
        if (input.files && input.files.length > 0) {
            form.submit();
        }
    });
})();
