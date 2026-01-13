// js/register.js
$(function () {

  // Helper: check password strength
  function isStrongPassword(password) {
    const hasMinLength = password.length >= 12;
    const hasUpper     = /[A-Z]/.test(password);
    const hasLower     = /[a-z]/.test(password);
    const hasNumber    = /[0-9]/.test(password);
    const hasSpecial   = /[^A-Za-z0-9]/.test(password); // any non-alphanumeric

    return hasMinLength && hasUpper && hasLower && hasNumber && hasSpecial;
  }

  // Toggle main password visibility
  $('#togglePassword').on('click', function () {
    const input = $('#password');
    const type = input.attr('type') === 'password' ? 'text' : 'password';
    input.attr('type', type);
    $(this).text(type === 'password' ? 'Show' : 'Hide');
  });

  // Toggle confirm password visibility
  $('#toggleConfirmPassword').on('click', function () {
    const input = $('#confirmPassword');
    const type = input.attr('type') === 'password' ? 'text' : 'password';
    input.attr('type', type);
    $(this).text(type === 'password' ? 'Show' : 'Hide');
  });

  // Form submit
  $('#registerForm').on('submit', function (e) {
    e.preventDefault();

    const name     = $('#name').val().trim();
    const email    = $('#email').val().trim();
    const password = $('#password').val();
    const confirm  = $('#confirmPassword').val();

    // Frontend checks
    if (!name || !email || !password || !confirm) {
      $('#registerMsg').text('All fields are required');
      return;
    }

    if (password !== confirm) {
      $('#registerMsg').text('Passwords do not match.');
      return;
    }

    // NEW: password policy check
    if (!isStrongPassword(password)) {
      $('#registerMsg').text(
        'Password must be at least 12 characters and include ' +
        'one uppercase letter, one lowercase letter, one number, and one special character.'
      );
      return;
    }

    $('#registerMsg').text('Registering...');

    $.ajax({
      url: 'php/register.php',
      method: 'POST',
      dataType: 'json',
      data: {
        name: name,
        email: email,
        password: password,
        confirm: confirm
      },
      success: function (res) {
        console.log('REGISTER RESPONSE:', res);
        if (res.success) {
          $('#registerMsg').text(res.message || 'Registration successful! Redirecting...');
          setTimeout(() => {
            window.location.href = 'login.html';
          }, 1000);
        } else {
          $('#registerMsg').text(res.message || 'Registration failed.');
        }
      },
      error: function () {
        $('#registerMsg').text('Server error. Try again.');
      }
    });
  });
});
