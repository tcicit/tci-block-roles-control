document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-category').forEach(function (categoryToggle) {
        categoryToggle.addEventListener('change', function () {
            const category = this.getAttribute('data-category');
            document.querySelectorAll('.block-checkbox.' + category).forEach(function (checkbox) {
                checkbox.checked = categoryToggle.checked;
            });
        });
    });
});