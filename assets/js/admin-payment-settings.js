(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (error) {
            return fallback;
        }
    }

    document.querySelectorAll('.edit-control-number-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var data = parseJSON(this.dataset.network, {});
            var setValue = function (id, value, fallback) {
                var el = document.getElementById(id);
                if (!el) return;
                el.value = value != null && value !== '' ? value : (fallback || '');
            };

            setValue('editControlId', data.id, '');
            setValue('editControlNetworkName', data.network_name, '');
            setValue('editControlNumber', data.control_number, '');
            setValue('editControlCompanyName', data.company_name, '');
            setValue('editControlSortOrder', data.sort_order, '0');
            setValue('editControlInfo', data.info, '');

            var activeInput = document.getElementById('editControlActive');
            if (activeInput) {
                activeInput.checked = String(data.is_active || '1') === '1';
            }
        });
    });
})();
