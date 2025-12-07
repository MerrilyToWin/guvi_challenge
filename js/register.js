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

$(function () {
  $('#registerForm').on('submit', function (e) {
    e.preventDefault();

    const name = $('#name').val().trim();
    const email = $('#email').val().trim();
    const password = $('#password').val();
    const confirmPassword = $('#confirmPassword').val();

    if (password !== confirmPassword) {
      $('#registerMsg').text('Passwords do not match.');
      return;
    }



    $('#registerMsg').text('Registering...');

    // Toggle main password visibility

    $.ajax({
      url: 'php/register.php',
      method: 'POST',
      dataType: 'json',
      data: { name, email, password },
      success: function (res) {
        console.log('SUCCESS:', res);
        if (res.success) {
          $('#registerMsg').text('Registration successful! Redirecting to login...');
          setTimeout(() => {
            window.location.href = 'login.html';
          }, 1000);
        } else {
          $('#registerMsg').text(res.message || 'Registration failed.');
        }
      },
    });
  });
});
