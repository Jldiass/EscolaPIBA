/**
 * Animação de entrada: os cartões aparecem com fade e sobem
 * quando entram na tela (IntersectionObserver).
 * Só anima o que ainda está fora da tela, para a página não "piscar".
 */
(function () {
    const semAnimacao = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (semAnimacao || !('IntersectionObserver' in window)) return;

    const observador = new IntersectionObserver(function (itens) {
        itens.forEach(function (item) {
            if (item.isIntersecting) {
                item.target.classList.remove('antes');
                observador.unobserve(item.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.cartao, .agenda-dia').forEach(function (el, i) {
        if (el.getBoundingClientRect().top > window.innerHeight) {
            el.classList.add('revelar', 'antes');
            el.style.transitionDelay = (i % 4) * 70 + 'ms';
            observador.observe(el);
        }
    });
})();
