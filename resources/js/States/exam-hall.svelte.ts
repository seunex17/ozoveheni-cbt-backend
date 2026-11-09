import axios from "axios";

export type HallParticipant = {
    attempt_id: number;
    student: { name: string; reg_no: string; photo_url: string | null };
    status: "ongoing" | "submitted";
};

type HallEvent = {
    operation: "upsert" | "reset";
    attempt_id: number | null;
    participant: HallParticipant | null;
    occurred_at: string;
};

export const hallState = $state({
    participants: [] as HallParticipant[],
    loaded: false,
    connection: "connecting",
    snapshotError: false,
    refreshing: false,
});

let refreshCurrent: (() => Promise<void>) | undefined;
export const refreshExamHall = () => refreshCurrent?.();

export function startExamHallMonitor() {
    let stopped = false;
    let fetching = false;
    let refreshAgain = false;
    let fallback: ReturnType<typeof setTimeout> | undefined;
    let refreshTimer: ReturnType<typeof setTimeout> | undefined;
    let pending: HallEvent[] = [];
    let watermark = "";
    const versions = new Map<number, string>();
    const controller = new AbortController();

    function apply(event: HallEvent) {
        if (event.operation !== "upsert" || !event.participant || event.occurred_at <= watermark) return;
        const incoming = event.participant;
        if (event.occurred_at <= (versions.get(incoming.attempt_id) ?? "")) return;
        const index = hallState.participants.findIndex((row) => row.attempt_id === incoming.attempt_id);
        // Completion is terminal even if older queued events arrive out of order.
        if (index !== -1 && hallState.participants[index].status === "submitted" && incoming.status === "ongoing") return;
        if (index === -1) hallState.participants.push(incoming);
        else hallState.participants[index] = incoming;
        versions.set(incoming.attempt_id, event.occurred_at);
    }

    function scheduleRefresh() {
        if (!stopped && !refreshTimer) {
            refreshTimer = setTimeout(() => {
                refreshTimer = undefined;
                void refresh();
            }, 250);
        }
    }

    async function refresh() {
        if (stopped) return;
        if (fetching) { refreshAgain = true; return; }
        fetching = true;
        hallState.refreshing = true;
        pending = [];
        try {
            const { data } = await axios.get("/dashboard/exam-hall/snapshot", { signal: controller.signal });
            if (stopped) return;
            if (!Array.isArray(data.participants)) throw new Error("Invalid hall snapshot");
            hallState.participants = data.participants;
            watermark = data.snapshot_started_at;
            versions.clear();
            for (const event of pending) apply(event);
            hallState.loaded = true;
            hallState.snapshotError = false;
        } catch {
            if (!stopped) hallState.snapshotError = true;
        } finally {
            fetching = false;
            if (refreshCurrent === refresh) hallState.refreshing = false;
            pending = [];
            if (refreshAgain && !stopped) { refreshAgain = false; scheduleRefresh(); }
        }
    }

    refreshCurrent = refresh;
    const channel = window.Echo?.private("exam-hall.monitor");
    hallState.connection = channel ? "connecting" : "unavailable";
    channel?.listen("ExamHallUpdated", (event: HallEvent) => {
        if (stopped) return;
        if (event.operation === "upsert") {
            if (fetching) pending.push(event);
            apply(event);
        } else if (event.operation === "reset") {
            // Reconcile with the database, including students admitted just after a sweep.
            scheduleRefresh();
        }
    }).subscribed(() => {
        if (stopped) return;
        clearTimeout(fallback);
        hallState.connection = "connected";
        void refresh();
    }).error(() => {
        if (stopped) return;
        clearTimeout(fallback);
        hallState.connection = "unavailable";
        if (!hallState.loaded && !fetching) void refresh();
    });

    const connection = window.Echo?.connector?.pusher?.connection;
    const onStateChange = ({ current }: { current: string }) => {
        if (!stopped && current !== "connected") hallState.connection = current;
        // Subscription success handles the reconnect snapshot once.
    };
    connection?.bind("state_change", onStateChange);
    // One fallback snapshot if the socket cannot subscribe; this is not a poll.
    if (!channel) void refresh();
    else fallback = setTimeout(() => { if (!stopped && !fetching && hallState.connection !== "connected") void refresh(); }, 2000);

    return () => {
        stopped = true;
        controller.abort();
        clearTimeout(fallback);
        clearTimeout(refreshTimer);
        if (refreshCurrent === refresh) refreshCurrent = undefined;
        connection?.unbind("state_change", onStateChange);
        window.Echo?.leave("exam-hall.monitor");
    };
}
