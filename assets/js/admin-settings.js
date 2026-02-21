(function () {
    var input = document.getElementById('adminProfilePhotoInput');
    var preview = document.getElementById('adminProfilePhotoPreview');

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        if (!input.files || !input.files[0]) return;
        var objectUrl = URL.createObjectURL(input.files[0]);
        preview.src = objectUrl;
    });
})();

