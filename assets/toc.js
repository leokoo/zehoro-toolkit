document.addEventListener('DOMContentLoaded', function() {
    const tocWrappers = document.querySelectorAll('[data-lkst-toc]');
    if (!tocWrappers.length) return;

    tocWrappers.forEach(wrapper => {
        const toggle = wrapper.querySelector('.lkst-toc-toggle');
        const activeText = wrapper.querySelector('.lkst-toc-active-text');
        const links = wrapper.querySelectorAll('.lkst-toc-list a');
        
        const header = wrapper.querySelector('.lkst-toc-header');
        if (header) {
            header.addEventListener('click', () => {
                wrapper.classList.toggle('is-open');
            });
        }

        // Close dropdown when a link is clicked (useful on mobile)
        links.forEach(link => {
            link.addEventListener('click', () => {
                wrapper.classList.remove('is-open');
            });
        });

        // Scroll spy logic
        const headings = [];
        links.forEach(link => {
            const id = link.getAttribute('href').substring(1);
            const el = document.getElementById(id);
            if (el) {
                headings.push({ id: id, el: el, text: link.textContent, linkEl: link });
            }
        });

        if (!headings.length) return;

        let ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    updateActiveHeading();
                    ticking = false;
                });
                ticking = true;
            }
        });

        function updateActiveHeading() {
            let currentId = '';
            let currentText = 'Sections in this article';
            let activeLink = null;
            
            // Offset for sticky headers
            const scrollOffset = 150; 

            for (let i = headings.length - 1; i >= 0; i--) {
                const heading = headings[i];
                const rect = heading.el.getBoundingClientRect();
                
                // If the heading has scrolled past the top offset
                if (rect.top <= scrollOffset) {
                    currentId = heading.id;
                    currentText = heading.text;
                    activeLink = heading.linkEl;
                    break;
                }
            }

            // We use a custom attribute to track the actual plain text since innerHTML will contain duplicated spans
            const prevText = activeText.getAttribute('data-current-text') || '';
            
            if (activeText && prevText !== currentText) {
                activeText.setAttribute('data-current-text', currentText);
                activeText.innerHTML = currentText; // Reset to plain text first
                
                activeText.classList.remove('is-marquee');
                activeText.style.animation = 'none';
                
                setTimeout(() => {
                    const parent = activeText.parentElement;
                    if (activeText.scrollWidth > parent.clientWidth) {
                        // It overflows! Let's duplicate it for continuous scrolling.
                        const gap = 40; // 40px gap between the two copies
                        activeText.innerHTML = `<span>${currentText}</span><span style="padding-left: ${gap}px" aria-hidden="true">${currentText}</span>`;
                        
                        // We need to translate by exactly the width of the first span + the gap
                        const firstSpan = activeText.children[0];
                        const dist = firstSpan.getBoundingClientRect().width + gap;
                        activeText.style.setProperty('--scroll-dist', `-${dist}px`);
                        
                        void activeText.offsetWidth; // Reflow
                        activeText.style.animation = '';
                        activeText.classList.add('is-marquee');
                    }
                }, 50);
            }

            links.forEach(link => link.classList.remove('is-active'));
            if (activeLink) {
                activeLink.classList.add('is-active');
            }
        }
        
        // On mobile, start collapsed (PHP outputs is-open, mobile shows dropdown)
        if (window.innerWidth <= 768) {
            wrapper.classList.remove('is-open');
        }

        // Init state
        updateActiveHeading();
    });
});