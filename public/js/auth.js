document.addEventListener("DOMContentLoaded", function () {

    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    if (toggle && password) {

        toggle.addEventListener("click", function () {

            if (password.type === "password") {

                password.type = "text";
                toggle.innerHTML = "🙈";

            } else {

                password.type = "password";
                toggle.innerHTML = "👁";

            }

        });

    }

});
