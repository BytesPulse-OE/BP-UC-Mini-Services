(function () {
    function lockViewportHeight() {
        const viewportHeight = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${viewportHeight}px`);
    }

    const config = window.BPUCMSData || {};
    let lang = 'el';
    const text = config.translations || {};
    const countdownEnabled = !!config.countdownEnabled;
    const countdownStart = config.countdownStart || '';
    const countdownEnd = config.countdownEnd || '';
    const contactEmail = config.contactEmail || '';

    lockViewportHeight();
    window.addEventListener('resize', lockViewportHeight, { passive: true });
    window.addEventListener('orientationchange', lockViewportHeight, { passive: true });

    document.addEventListener('gesturestart', (event) => event.preventDefault());
    document.addEventListener('touchmove', (event) => {
        if (event.scale && event.scale !== 1) {
            event.preventDefault();
        }
    }, { passive: false });

    const langToggle = document.getElementById('langToggle');
    const themeToggle = document.getElementById('themeToggle');
    const title = document.getElementById('title');
    const subtitle = document.getElementById('subtitle');
    const contactMsg = document.getElementById('contactMsg');
    const countdownElement = document.getElementById('countdown');

    if (langToggle) {
        langToggle.onclick = () => {
            lang = lang === 'el' ? 'en' : 'el';
            langToggle.innerText = lang === 'el' ? 'EN' : 'EL';
            if (title) title.innerText = text[lang].title;
            if (subtitle) subtitle.innerText = text[lang].subtitle;
            if (contactMsg) contactMsg.innerHTML = `${text[lang].contact} <a href="mailto:${contactEmail}">${contactEmail}</a>`;
            updateCountdown();
        };
    }

    if (themeToggle) {
        themeToggle.onclick = () => {
            document.body.classList.toggle('light');
            themeToggle.innerText = document.body.classList.contains('light') ? 'Dark' : 'Light';
        };
    }

    function updateCountdown() {
        if (!countdownEnabled || !countdownElement || !countdownEnd) {
            return;
        }

        const now = new Date();
        const startDate = countdownStart ? new Date(countdownStart) : null;
        const endDate = new Date(countdownEnd);

        if (startDate && now < startDate) {
            const diff = startDate - now;
            const d = Math.floor(diff / (1000 * 60 * 60 * 24));
            const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const m = Math.floor((diff / (1000 * 60)) % 60);
            const s = Math.floor((diff / 1000) % 60);
            countdownElement.innerHTML = `${text[lang].starts_in} ${d} ${text[lang].days} • ${h} ${text[lang].hours} • ${m} ${text[lang].minutes} • ${s} ${text[lang].seconds}`;
            return;
        }

        if (now > endDate) {
            countdownElement.innerText = text[lang].done;
            return;
        }

        const diff = endDate - now;
        const d = Math.floor(diff / (1000 * 60 * 60 * 24));
        const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
        const m = Math.floor((diff / (1000 * 60)) % 60);
        const s = Math.floor((diff / 1000) % 60);
        countdownElement.innerHTML = `${d} ${text[lang].days} • ${h} ${text[lang].hours} • ${m} ${text[lang].minutes} • ${s} ${text[lang].seconds}`;
    }

    if (countdownEnabled) {
        setInterval(updateCountdown, 1000);
        updateCountdown();
    }

    const starCanvas = document.getElementById('starfield');
    const starCtx = starCanvas ? starCanvas.getContext('2d') : null;
    let stars = [];
    let starCount = 250;
    let mouseX = 0.5;
    let mouseY = 0.5;

    function resizeStarfield() {
        if (!starCanvas) return;
        starCanvas.width = window.innerWidth * 2;
        starCanvas.height = window.innerHeight * 2;
    }

    if (starCanvas && starCtx) {
        resizeStarfield();
        window.addEventListener('resize', resizeStarfield);
        for (let i = 0; i < starCount; i++) {
            stars.push({
                x: Math.random() * starCanvas.width,
                y: Math.random() * starCanvas.height,
                size: Math.random() * 2 + 0.5,
                speed: Math.random() * 0.4 + 0.1
            });
        }

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX / window.innerWidth - 0.5;
            mouseY = e.clientY / window.innerHeight - 0.5;
        });

        (function drawStars() {
            starCtx.clearRect(0, 0, starCanvas.width, starCanvas.height);
            for (const s of stars) {
                s.x += s.speed * mouseX * 2;
                s.y += s.speed * mouseY * 2;
                if (s.x < 0) s.x = starCanvas.width;
                if (s.x > starCanvas.width) s.x = 0;
                if (s.y < 0) s.y = starCanvas.height;
                if (s.y > starCanvas.height) s.y = 0;
                starCtx.fillStyle = 'rgba(255,255,255,0.8)';
                starCtx.fillRect(s.x, s.y, s.size, s.size);
            }
            requestAnimationFrame(drawStars);
        })();
    }

    const canvas = document.getElementById('pulseCanvas');
    const ctx = canvas ? canvas.getContext('2d') : null;
    let width = 0;
    let height = 0;
    let pulseMouseX = 0.5;
    let time = 0;

    function resizeCanvas() {
        if (!canvas || !ctx) return;
        const rect = canvas.getBoundingClientRect();
        width = rect.width;
        height = rect.height;
        canvas.width = width * window.devicePixelRatio;
        canvas.height = height * window.devicePixelRatio;
        ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
    }

    if (canvas && ctx) {
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            pulseMouseX = (e.clientX - rect.left) / rect.width;
        });

        (function drawPulse() {
            ctx.clearRect(0, 0, width, height);
            const midY = height / 2;
            const amplitude = 15 + pulseMouseX * 10;
            const length = width;
            const segments = 90;
            const speed = 1.2;
            const gradient = ctx.createLinearGradient(0, 0, width, 0);
            gradient.addColorStop(0, '#1fb6ff');
            gradient.addColorStop(0.5, '#ff7b00');
            gradient.addColorStop(1, '#ffdd55');
            ctx.lineWidth = 2.2;
            ctx.strokeStyle = gradient;
            ctx.beginPath();
            for (let i = 0; i <= segments; i++) {
                const x = (i / segments) * length;
                const progress = i / segments;
                const baseWave = Math.sin(progress * 4 * Math.PI + time * speed);
                const pulseSpike = Math.exp(-Math.pow((progress - 0.5) * 6, 2)) * 1.2;
                const y = midY + (baseWave * amplitude + pulseSpike * amplitude * 1.4);
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.shadowColor = 'rgba(255, 123, 0, 0.5)';
            ctx.shadowBlur = 14;
            ctx.stroke();
            ctx.shadowBlur = 0;
            time += 0.008;
            requestAnimationFrame(drawPulse);
        })();
    }
})();
