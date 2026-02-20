// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
        const target = this.getAttribute("href");
        if (target && target !== "#") {
            e.preventDefault();
            const section = document.querySelector(target);
            if (section) {
                section.scrollIntoView({ behavior: "smooth" });
            }
        }
    });
});

// Professional header behavior on scroll
const siteHeader = document.querySelector(".site-header");
if (siteHeader) {
    const syncHeaderState = () => {
        siteHeader.classList.toggle("is-scrolled", window.scrollY > 24);
    };
    syncHeaderState();
    window.addEventListener("scroll", syncHeaderState, { passive: true });
}

// Hero image slideshow (every 3 seconds by default)
let heroImage = document.querySelector(".hero-media-image[data-hero-images]");
if (heroImage) {
    let heroImages = [];
    try {
        heroImages = JSON.parse(heroImage.dataset.heroImages || "[]");
    } catch (error) {
        heroImages = [];
    }

    if (heroImages.length > 1) {
        const heroCard = heroImage.closest(".hero-media-card");
        let currentIndex = heroImages.indexOf(heroImage.getAttribute("src"));
        if (currentIndex < 0) currentIndex = 0;
        let isAnimating = false;

        const interval = Math.max(parseInt(heroImage.dataset.heroInterval || "3000", 10) || 3000, 1200);
        const transitionMs = 650;

        window.setInterval(() => {
            if (!heroCard || isAnimating) return;
            isAnimating = true;

            currentIndex = (currentIndex + 1) % heroImages.length;
            const nextSrc = heroImages[currentIndex];

            // Preload first to avoid showing alt/text flash during slide.
            const preloader = new Image();
            preloader.onload = () => {
                const nextImage = heroImage.cloneNode(false);
                nextImage.setAttribute("src", nextSrc);
                nextImage.classList.add("next-slide");
                heroCard.appendChild(nextImage);

                window.requestAnimationFrame(() => {
                    heroImage.classList.add("is-slide-out");
                    nextImage.classList.add("is-active");
                });

                window.setTimeout(() => {
                    heroImage.remove();
                    nextImage.classList.remove("next-slide", "is-active");
                    heroImage = nextImage;
                    isAnimating = false;
                }, transitionMs);
            };

            preloader.onerror = () => {
                isAnimating = false;
            };

            preloader.src = nextSrc;
        }, interval);
    }
}
