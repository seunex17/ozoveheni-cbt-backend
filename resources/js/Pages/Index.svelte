<script lang="ts">
    import { Form } from "@inertiajs/svelte";
    import { Lock, User } from "lucide-svelte";
</script>

<main class="w-screen h-screen flex flex-col justify-center items-center">
    <div
        class="w-full max-w-md flex flex-col justify-center items-center space-y-2"
    >
        <div>
            <img src="/images/logo.jpg" class="w-20" alt="" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900">OZOVEHENI</h1>
        <p class="text-slate-600">Staff Portal</p>
    </div>
    <div class="card w-96 bg-base-100 shadow-sm">
        <div class="card-body">
            <Form
                class="space-y-5"
                action="/login"
                method="POST"
                disableWhileProcessing
            >
                <div>
                    <label for="id" class="input">
                        <User class="opacity-50" size="20" />
                        <input type="text" name="name" placeholder="Username" />
                    </label>
                </div>
                <div>
                    <label for="cource_code" class="input">
                        <Lock class="opacity-50" size="20" />
                        <input
                            type="password"
                            name="password"
                            placeholder="Password"
                        />
                    </label>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-block"
                        >Login</button
                    >
                </div>
            </Form>
        </div>
    </div>
</main>
