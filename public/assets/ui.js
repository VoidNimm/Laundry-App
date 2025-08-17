// Minimal GSAP-driven UI interactions and scroll animations
// Requires gsap and ScrollTrigger loaded before this file

(function () {
  document.addEventListener('DOMContentLoaded', function () {
    // Smooth scroll behavior
    try { document.documentElement.style.scrollBehavior = 'smooth'; } catch (e) {}
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Sticky navbar background toggle
    const navbar = document.querySelector('[data-nav]');
    if (navbar) {
      const setNavState = () => {
        if (window.scrollY > 8) {
          navbar.classList.add('backdrop-blur','bg-black/50','shadow-lg','border-b','border-white/10');
        } else {
          navbar.classList.remove('backdrop-blur','bg-black/50','shadow-lg','border-b','border-white/10');
        }
      };
      setNavState();
      window.addEventListener('scroll', setNavState, { passive: true });
    }

    if (typeof gsap === 'undefined') return;
    if (prefersReduced) return;

    if (gsap && window.ScrollTrigger) {
      gsap.registerPlugin(ScrollTrigger);
    }

    // Intro animations
    const introTimeline = gsap.timeline({
      defaults: { duration: 0.8, ease: 'power3.out' }
    });
    const hero = document.querySelector('[data-hero]');
    if (hero) {
      const headline = hero.querySelectorAll('[data-animate="headline"]');
      const sub = hero.querySelectorAll('[data-animate="sub"]');
      const cta = hero.querySelectorAll('[data-animate="cta"]');
      introTimeline
        .from(headline, { y: 24, opacity: 0, stagger: 0.06 })
        .from(sub, { y: 16, opacity: 0 }, '-=0.4')
        .from(cta, { y: 12, opacity: 0 }, '-=0.5');
    }

    // Generic scroll reveal
    document.querySelectorAll('[data-animate="fade-up"]').forEach((el) => {
      gsap.from(el, {
        y: 32,
        opacity: 0,
        duration: 0.9,
        ease: 'power3.out',
        scrollTrigger: {
          trigger: el,
          start: 'top 80%'
        }
      });
    });

    document.querySelectorAll('[data-animate="slide-in"]').forEach((el) => {
      gsap.from(el, {
        x: 40,
        opacity: 0,
        duration: 0.9,
        ease: 'power3.out',
        scrollTrigger: {
          trigger: el,
          start: 'top 80%'
        }
      });
    });

    // Parallax shapes
    document.querySelectorAll('[data-parallax]').forEach((el) => {
      const speed = parseFloat(el.getAttribute('data-parallax')) || 0.2;
      gsap.to(el, {
        yPercent: speed * 100,
        ease: 'none',
        scrollTrigger: {
          trigger: el,
          start: 'top bottom',
          end: 'bottom top',
          scrub: true
        }
      });
    });

    // Mobile menu toggle
    const menuBtn = document.querySelector('[data-menu-btn]');
    const menuPanel = document.querySelector('[data-menu-panel]');
    if (menuBtn && menuPanel) {
      menuBtn.addEventListener('click', () => {
        const open = menuPanel.getAttribute('data-open') === 'true';
        menuPanel.setAttribute('data-open', String(!open));
        menuPanel.classList.toggle('translate-x-0');
      });
    }
  });
})();


