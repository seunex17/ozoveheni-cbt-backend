type InstallPrompt = Event & {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
};

export const pwaState = $state({
    installed: false,
    installing: false,
    secure: true,
    error: "",
});

let deferredPrompt: InstallPrompt | null = null;

export function initializePwa() {
    pwaState.secure = window.isSecureContext;
    pwaState.installed = window.matchMedia("(display-mode: standalone)").matches
        || Boolean((navigator as Navigator & { standalone?: boolean }).standalone);
    window.addEventListener("beforeinstallprompt", (event) => {
        event.preventDefault();
        deferredPrompt = event as InstallPrompt;
        pwaState.error = "";
    });
    window.addEventListener("appinstalled", () => {
        deferredPrompt = null;
        pwaState.installed = true;
    });
    if (window.isSecureContext && "serviceWorker" in navigator) {
        const register = () => navigator.serviceWorker.register("/sw.js", { scope: "/", updateViaCache: "none" })
            .catch(() => { pwaState.error = "App setup could not finish. Check the server connection and reload."; });
        if (document.readyState === "complete") void register();
        else window.addEventListener("load", register, { once: true });
    }
}

export async function installPwa(): Promise<boolean> {
    if (!deferredPrompt || pwaState.installing) return false;
    const prompt = deferredPrompt;
    deferredPrompt = null;
    pwaState.installing = true;
    try {
        await prompt.prompt();
        const choice = await prompt.userChoice;
        if (choice.outcome === "accepted") pwaState.installed = true;
        return true;
    } catch {
        pwaState.error = "Installation could not start. Use your browser’s install option or try again.";
        return false;
    } finally {
        pwaState.installing = false;
    }
}
