$('#togglePassword').on('click', function () {
        const input = $('#password');
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).text(type === 'password' ? 'Show' : 'Hide');
    });
$(function () {
  $('#loginForm').on('submit', function (e) {
    e.preventDefault();

    const email = $('#email').val().trim();
    const password = $('#password').val();

    $('#loginMsg').text('Logging in...');

    $.ajax({
      url: 'php/login.php',
      method: 'POST',
      dataType: 'json',
      data: { email, password },
      success: function (res) {
        if (res.success) {
          // Save token + minimal info in localStorage
          localStorage.setItem('sessionToken', res.token);
          localStorage.setItem('userId', res.user.id);
          localStorage.setItem('userName', res.user.name);
          localStorage.setItem('userEmail', res.user.email);

          $('#loginMsg').text('Login successful! Redirecting...');
          setTimeout(() => {
            window.location.href = 'profile.html';
          }, 800);
        } else {
          $('#loginMsg').text(res.message || 'Invalid email or password.');
        }
      },
      error: function () {
        $('#loginMsg').text('Server error. Try again.');
      }
    });
  });
});
