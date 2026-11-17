/**
 * Nota musical 3D (colcheia ♪) girando no painel, feita com Three.js.
 *
 * Uma colcheia tem três partes:
 *   cabeça  -> uma esfera "achatada" e inclinada
 *   haste   -> um cilindro fino
 *   bandeira-> um tubo curvo saindo do topo da haste
 * Cada parte recebe uma cor do arco-íris da identidade visual.
 * Se o Three.js não carregar (sem internet), mostramos ♪ simples.
 */
(function () {
    const palco = document.getElementById('palco');
    if (!palco) return;

    if (typeof THREE === 'undefined') {
        palco.insertAdjacentHTML('afterbegin', '<div class="reserva">♪</div>');
        return;
    }

    const cena = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 100);
    camera.position.set(0, 0.4, 9);

    const render = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    render.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    palco.prepend(render.domElement);

    // Luzes: uma ambiente suave + duas direcionais para dar volume
    cena.add(new THREE.AmbientLight(0xffffff, 0.65));
    const luz1 = new THREE.DirectionalLight(0xffffff, 0.8);
    luz1.position.set(4, 6, 6);
    cena.add(luz1);
    const luz2 = new THREE.DirectionalLight(0xff4fa3, 0.35);
    luz2.position.set(-6, -2, 3);
    cena.add(luz2);

    const material = (cor) => new THREE.MeshStandardMaterial({ color: cor, roughness: 0.35, metalness: 0.1 });

    const nota = new THREE.Group();

    // Cabeça da nota
    const cabeca = new THREE.Mesh(new THREE.SphereGeometry(0.75, 48, 32), material(0x7b5cff));
    cabeca.scale.set(1.3, 0.95, 0.9);
    cabeca.rotation.z = 0.45;
    cabeca.position.set(-0.55, -1.6, 0);
    nota.add(cabeca);

    // Haste
    const haste = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.11, 3.6, 24), material(0xff6b2c));
    haste.position.set(0.32, 0.2, 0);
    nota.add(haste);

    // Bandeira: curva que sai do topo da haste e desce para a direita
    const curva = new THREE.CubicBezierCurve3(
        new THREE.Vector3(0.32, 2.0, 0),
        new THREE.Vector3(1.4, 1.4, 0),
        new THREE.Vector3(1.6, 0.6, 0),
        new THREE.Vector3(1.1, -0.2, 0)
    );
    const bandeira = new THREE.Mesh(new THREE.TubeGeometry(curva, 48, 0.16, 16, false), material(0xff4fa3));
    nota.add(bandeira);

    // Pequenas esferas coloridas orbitando, como "notas soltas"
    const cores = [0x00b8a9, 0xffc93c, 0x2f8cff, 0x1fa463];
    const bolinhas = cores.map((cor, i) => {
        const b = new THREE.Mesh(new THREE.SphereGeometry(0.16, 24, 16), material(cor));
        b.userData.angulo = (i / cores.length) * Math.PI * 2;
        cena.add(b);
        return b;
    });

    cena.add(nota);

    function ajustarTamanho() {
        const w = palco.clientWidth, h = palco.clientHeight;
        render.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    window.addEventListener('resize', ajustarTamanho);
    ajustarTamanho();

    const parado = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let t = 0;

    // Loop de animação: roda ~60 vezes por segundo
    function animar() {
        t += 0.016;
        nota.rotation.y = t * 0.8;                    // gira
        nota.position.y = Math.sin(t * 1.4) * 0.15;   // flutua (bob)
        bolinhas.forEach((b, i) => {
            const a = b.userData.angulo + t * 0.9;
            b.position.set(Math.cos(a) * 2.6, Math.sin(a * 1.3 + i) * 1.2, Math.sin(a) * 1.4);
        });
        render.render(cena, camera);
        if (!parado) requestAnimationFrame(animar);
    }
    animar();
})();
