// js/profile.js

// 1) Banner & profile image preview (front-end only)
const bannerEl = document.getElementById('banner');
const bannerInput = document.getElementById('bannerInput');
const profileEl = document.getElementById('profileImage');
const profileInput = document.getElementById('profileInput');

if (bannerInput) {
  bannerInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (file) {
      bannerEl.style.backgroundImage = `url(${URL.createObjectURL(file)})`;
    }
  });
}

if (profileInput) {
  profileInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (file) {
      profileEl.style.backgroundImage = `url(${URL.createObjectURL(file)})`;
    }
  });
}

// 2) jQuery logic for profile data & auth
$(function () {
  const token = localStorage.getItem('sessionToken');

  if (!token) {
    window.location.href = 'login.html';
    return;
  }

  // Load profile data on page load
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

      // Name & email from Redis / MySQL session
      $('#displayName').text(profile.name || '');
      $('#email').val(profile.email || '');

      // Mongo fields
      $('#age').val(profile.age || '');
      $('#dob').val(profile.dob || '');
      $('#contact').val(profile.contact || '');

      // Images from Mongo (paths)
      if (profile.banner_pic) {
        $('#banner').css('background-image', `url(${profile.banner_pic})`);
      }
      if (profile.profile_pic) {
        $('#profileImage').css('background-image', `url(${profile.profile_pic})`);
      }

      $('#profileMsg').text('');
    },
    error: function (jqXHR, textStatus, errorThrown) {
      console.error('Profile load error:', textStatus, errorThrown, jqXHR.responseText);
      $('#profileMsg').text('Failed to load profile. Please refresh.');
    }
  });

  // Handle profile update (with images)
  $('#profileForm').on('submit', function (e) {
    e.preventDefault();

    const age = $('#age').val();
    const dob = $('#dob').val();
    const contact = $('#contact').val();

    $('#profileMsg').text('Saving...');

    const formData = new FormData();
    formData.append('token', token);
    formData.append('age', age);
    formData.append('dob', dob);
    formData.append('contact', contact);

    if (profileInput && profileInput.files[0]) {
      formData.append('profile_image', profileInput.files[0]);
    }
    if (bannerInput && bannerInput.files[0]) {
      formData.append('banner_image', bannerInput.files[0]);
    }

    $.ajax({
      url: 'php/profile.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          $('#profileMsg').text(res.message || 'Profile updated successfully.');
        } else {
          $('#profileMsg').text(res.message || 'Profile update failed.');
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error('Profile update error:', textStatus, errorThrown, jqXHR.responseText);
        $('#profileMsg').text('Server error while updating profile.');
      }
    });
  });

  // Logout handler
  $('#logoutBtn').on('click', function (e) {
    e.preventDefault();

    const token = localStorage.getItem('sessionToken');
    localStorage.clear();

    if (token) {
      $.ajax({
        url: 'php/profile.php',
        method: 'POST',
        dataType: 'json',
        data: {
          logout: 1,
          token: token
        },
        complete: function () {
          window.location.href = 'login.html';
        }
      });
    } else {
      window.location.href = 'login.html';
    }
  });
});
