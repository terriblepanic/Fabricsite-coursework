document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll("form[data-validate]");

    forms.forEach(form => {
        form.addEventListener("submit", function (e) {
            let valid = true;
            const errors = [];

            form.querySelectorAll("[required]").forEach(field => {
                const value = field.value.trim();
                if (!value) {
                    valid = false;
                    errors.push(`Поле "${getFieldName(field)}" обязательно для заполнения.`);
                    field.classList.add("input-error");
                } else {
                    field.classList.remove("input-error");
                }

                // Email валидация
                if (field.type === "email" && !/^\S+@\S+\.\S+$/.test(value)) {
                    valid = false;
                    errors.push("Некорректный email.");
                    field.classList.add("input-error");
                }

                // Пароли совпадают
                if (field.name === "confirm_password") {
                    const password = form.querySelector('input[name="password"]');
                    if (password && value !== password.value) {
                        valid = false;
                        errors.push("Пароли не совпадают.");
                        field.classList.add("input-error");
                    }
                }
            });

            if (!valid) {
                e.preventDefault();
                showErrorMessages(form, errors);
            }
        });
    });

    function showErrorMessages(form, messages) {
        let container = form.querySelector(".js-errors");
        if (!container) {
            container = document.createElement("div");
            container.className = "js-errors";
            container.style.color = "red";
            container.style.marginTop = "10px";
            form.prepend(container);
        }
        container.innerHTML = `<ul>${messages.map(m => `<li>${m}</li>`).join("")}</ul>`;
    }

    function getFieldName(field) {
        return field.labels?.[0]?.textContent || field.name;
    }


});

