document.querySelectorAll('[data-bs-toggle="collapse"]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const destino = document.querySelector(boton.getAttribute('data-bs-target'));
        if (!destino) {
            return;
        }

        const abierto = destino.classList.toggle('show');
        boton.setAttribute('aria-expanded', String(abierto));
    });
});
