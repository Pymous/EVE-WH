import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Tippy
import tippy from "tippy.js";
import "tippy.js/dist/tippy.css";
window.tippy = tippy;
