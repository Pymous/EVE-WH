import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Tippy
import tippy from "tippy.js";
import "tippy.js/dist/tippy.css";
window.tippy = tippy;

const observer = new MutationObserver(() => {
    tippy("[data-tippy-content]", {
        content(reference) {
            return reference.getAttribute("data-tippy-content");
        },
        allowHTML: true,
    });
});

observer.observe(document.body, {
    childList: true,
    subtree: true,
});

tippy("[data-tippy-content]", {
    content(reference) {
        return reference.getAttribute("data-tippy-content");
    },
});
