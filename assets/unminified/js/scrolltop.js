import { store, getContext, getElement, getConfig, withScope } from '@wordpress/interactivity';

const {
    hooks: {
        applyFilters
    }
} = wp;

const {
    fn: {
        getElement: getDOMElement,
        validateConfig,
    },
} = wecodeart;

const NAME = 'scrolltop';
const NAMESPACE = `wecodeart/${NAME}`;

const { state, callbacks } = store(NAMESPACE, {
    // Public methods!
    actions: {
        onScroll: () => {
            const context = getContext();
            const { showOffset = 0 } = callbacks.getConfig();

            if (context.animationFrame) {
                cancelAnimationFrame(context.animationFrame);
            }

            if (window.scrollY >= showOffset) {
                context.isVisible = true;
                return;
            }

            context.isVisible = false;
        },
        animateScoll() {
            const context = getContext();

            if (context.animationFrame) {
                cancelAnimationFrame(context.animationFrame);
            }

            context.startTime = performance.now();
            context.animationFrame = window.requestAnimationFrame(withScope(callbacks.loop));
        },
    },
    // Private, mostly!
    callbacks: {
        loop: (timestamp) => {
            const { duration, startTime } = callbacks.getConfig();
            const elapsedTime = timestamp - startTime;
            const position = callbacks.easing(elapsedTime);

            document.documentElement.scrollTop = position;
            document.body.scrollTop = position;

            if (elapsedTime < duration) {
                window.requestAnimationFrame(withScope(callbacks.loop));
            }
        },
        visibility: () => {
            const { ref } = getElement();
            const { isVisible } = getContext();

            if (typeof isVisible === 'boolean') {
                ref.classList[isVisible ? 'add' : 'remove']('wp-element-button--appear');
                ref.style.opacity = isVisible ? 'var(--wp--opacity)' : 0;
                ref.style.pointerEvents = isVisible ? 'all' : 'none';
            }
        },
        easing: (currentTime) => {
            const { duration } = callbacks.getConfig();
            const change = callbacks.getScrollTo() - callbacks.getScrollTop();

            currentTime /= duration;

            return -change * currentTime * (currentTime - 2) + callbacks.getScrollTop();
        },
        getScrollTop: () => {
            return document.documentElement.scrollTop || document.body.scrollTop;
        },
        getScrollTo: () => {
            const { target, offset = 0 } = callbacks.getConfig();

            return (target ? target.offsetTop : 0) - offset;
        },
        getConfig: () => {
            const context = getContext();
            const config = { ...state, ...context };

            config.target = config.target === 'html' ? document.documentElement : getDOMElement(config.target);

            return applyFilters('wecodeart.interactive.config', config, NAME);
        },
        validateConfig: () => validateConfig(NAME, callbacks.getConfig(), getConfig(NAMESPACE)),
    }
});