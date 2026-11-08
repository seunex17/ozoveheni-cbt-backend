import axios from "axios";

export type HallParticipant = {
    attempt_id: number;
    student: { name: string; reg_no: string; photo_url: string | null; department: string };
    exam: { course: string; code: string; title: string };
    started_at: string | null;
    last_seen_at: string | null;
    current_question_id: number | null;
    current_question_number: number | null;
    total_questions: number | null;
    last_sequence: number;
};

type HallEvent = {
    operation: "progress" | "refresh" | "remove" | "reset";
    attempt_id: number | null;
    progress: Partial<HallParticipant> & { last_sequence?: number };
};

export const hallState = $state({
    participants: [] as HallParticipant[],
    loaded: false,
    connection: "connecting",
    snapshotError: false,
    updatedAt: null as string | null,
    serverOffset: 0,
    staleAfterSeconds: 60,
});

export function startExamHallMonitor() {
    let stopped = false;
    let fetching = false;
    let refreshAgain = false;
    let refreshTimer: ReturnType<typeof setTimeout> | undefined;
    let pending: HallEvent[] = [];
    const controller = new AbortController();

    function scheduleRefresh() {
        if (!refreshTimer && !stopped) {
            refreshTimer = setTimeout(() => {
                refreshTimer = undefined;
                void refresh();
            }, 300);
        }
    }

    function applyProgress(event: HallEvent) {
        const row = hallState.participants.find((item) => item.attempt_id === event.attempt_id);
        if (!row) {
            scheduleRefresh();
            return;
        }
        if ((event.progress.last_sequence ?? 0) > row.last_sequence) {
            Object.assign(row, event.progress);
        }
    }

    async function refresh() {
        if (stopped) return;
        if (fetching) {
            refreshAgain = true;
            return;
        }
        fetching = true;
        pending = [];
        try {
            const { data } = await axios.get("/dashboard/exam-hall/snapshot", { signal: controller.signal });
            if (stopped) return;
            if (!Array.isArray(data.participants)) throw new Error("Invalid hall snapshot");
            hallState.participants = data.participants;
            hallState.serverOffset = Date.parse(data.server_time) - Date.now();
            hallState.staleAfterSeconds = data.stale_after_seconds;
            hallState.updatedAt = data.server_time;
            hallState.loaded = true;
            hallState.snapshotError = false;
            for (const event of pending) applyProgress(event);
        } catch {
            if (!stopped) hallState.snapshotError = true;
        } finally {
            fetching = false;
            pending = [];
            if (refreshAgain && !stopped) {
                refreshAgain = false;
                scheduleRefresh();
            }
        }
    }

    const channel = window.Echo?.private("exam-hall.monitor");
    hallState.connection = channel ? "connecting" : "unavailable";
    channel?.listen("ExamHallUpdated", (event: HallEvent) => {
        if (stopped) return;
        if (event.operation === "progress") {
            if (fetching) pending.push(event);
            applyProgress(event);
        } else {
            if (event.operation === "remove") {
                hallState.participants = hallState.participants.filter((row) => row.attempt_id !== event.attempt_id);
            }
            if (fetching) refreshAgain = true;
            scheduleRefresh();
        }
    }).subscribed(() => {
        hallState.connection = "connected";
        void refresh();
    }).error(() => {
        hallState.connection = "unavailable";
    });

    const connection = window.Echo?.connector?.pusher?.connection;
    const onStateChange = ({ current }: { current: string }) => {
        if (stopped) return;
        if (current !== "connected") hallState.connection = current;
        else void refresh();
    };
    connection?.bind("state_change", onStateChange);
    void refresh();
    const interval = setInterval(() => void refresh(), 30000);

    return () => {
        stopped = true;
        controller.abort();
        clearInterval(interval);
        clearTimeout(refreshTimer);
        connection?.unbind("state_change", onStateChange);
        window.Echo?.leave("exam-hall.monitor");
    };
}
