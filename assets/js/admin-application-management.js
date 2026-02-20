(function () {
    function fillView(app) {
        document.getElementById('viewAppId').textContent = app.id ?? '-';
        document.getElementById('viewAppUser').textContent = app.username ?? '-';
        document.getElementById('viewAppEmail').textContent = app.email ?? '-';
        document.getElementById('viewAppHostel').textContent = app.hostel_name ?? '-';
        document.getElementById('viewAppRoom').textContent = app.room_number ?? '-';
        document.getElementById('viewAppBed').textContent = app.bed_number || '-';
        document.getElementById('viewAppStatus').textContent = app.status ?? '-';
        document.getElementById('viewAppBookingDate').textContent = app.booking_date ?? '-';
        document.getElementById('viewAppCreated').textContent = app.created_at ?? '-';
    }

    document.querySelectorAll('.view-app-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillView(JSON.parse(this.dataset.app));
        });
    });

    document.querySelectorAll('.action-app-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var app = JSON.parse(this.dataset.app);
            var action = this.dataset.action;

            document.getElementById('applicationActionType').value = action;
            document.getElementById('applicationActionId').value = app.id;

            var isApprove = action === 'approve_application';
            document.getElementById('applicationActionTitle').textContent = isApprove ? 'Approve Application' : 'Reject Application';
            document.getElementById('applicationActionMessage').textContent =
                (isApprove ? 'Approve' : 'Reject') + ' application #' + app.id + ' for ' + (app.username || 'user') + '?';

            var confirmBtn = document.getElementById('applicationActionBtn');
            confirmBtn.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
            confirmBtn.textContent = isApprove ? 'Approve' : 'Reject';
        });
    });
})();
