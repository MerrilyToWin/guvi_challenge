// jQuery logic for profile data & auth
$(function () {
    const token = localStorage.getItem('sessionToken');

    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Load profile data
    $('#profileMsg').text('Loading profile...');

    $.ajax({
        url: 'php/profile.php',
        method: 'GET',
        dataType: 'json',
        data: { token: token },
        success: function (res) {
            if (!res.success) {
                localStorage.clear();
                window.location.href = 'login.html';
                return;
            }

            const profile = res.profile || {};

            $('#displayName').text(profile.name || '');
            $('#email').val(profile.email || '');
            $('#age').val(profile.age || '');
            $('#dob').val(profile.dob || '');
            $('#contact').val(profile.contact || '');

            $('#profileMsg').text('');
        },
        error: function () {
            $('#profileMsg').text('Failed to load profile. Please refresh.');
        }
    });

    // Update profile
    $('#profileForm').on('submit', function (e) {
        e.preventDefault();

        $('#profileMsg').text('Saving...');

        const formData = new FormData();
        formData.append('token', token);
        formData.append('age', $('#age').val());
        formData.append('dob', $('#dob').val());
        formData.append('contact', $('#contact').val());

        $.ajax({
            url: 'php/profile.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#profileMsg').text('');
                    const modal = new bootstrap.Modal(
                        document.getElementById('profileSuccessModal')
                    );
                    modal.show();
                } else {
                    $('#profileMsg').text(res.message || 'Profile update failed.');
                }
            },
            error: function () {
                $('#profileMsg').text('Server error while updating profile.');
            }
        });
    });

    // Logout
    $('#logoutBtn').on('click', function (e) {
        e.preventDefault();

        const token = localStorage.getItem('sessionToken');
        localStorage.clear();

        if (token) {
            $.ajax({
                url: 'php/profile.php',
                method: 'POST',
                dataType: 'json',
                data: { logout: 1, token: token },
                complete: function () {
                    window.location.href = 'login.html';
                }
            });
        } else {
            window.location.href = 'login.html';
        }
    });
});
