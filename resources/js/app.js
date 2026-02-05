import { createInertiaApp } from "@inertiajs/svelte";
import { mount } from "svelte";
import MainLayout from "./MainLayout.svelte";

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.svelte", { eager: true });
        let page = pages[`./Pages/${name}.svelte`];
        return { default: page.default, layout: page.layout || MainLayout };
    },
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },
});
