import "@fontsource/ropa-sans";
import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

// vue3-toastify CSS
import "vue3-toastify/dist/index.css";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        app.directive("tippy", {
            mounted(el, binding) {
                window.tippy(el, {
                    content(reference) {
                        return reference.getAttribute("data-tippy-content");
                    },
                    allowHTML: true,
                });
            },
            updated(el, binding) {
                window.tippy(el, {
                    content(reference) {
                        return reference.getAttribute("data-tippy-content");
                    },
                    allowHTML: true,
                });
            },
        });

        return app.mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
