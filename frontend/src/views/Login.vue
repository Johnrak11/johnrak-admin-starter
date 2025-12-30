<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto flex min-h-screen max-w-6xl items-center px-6">
      <div class="grid w-full grid-cols-1 gap-8 md:grid-cols-2">
        <div class="flex flex-col justify-center">
          <div class="text-4xl font-semibold">Johnrak Admin</div>
          <div class="mt-3 text-muted-foreground">
            Private dashboard for managing your portfolio and personal projects.
          </div>

          <div
            class="mt-6 rounded-lg border border-border bg-card p-4 text-sm text-card-foreground"
          >
            <div class="font-medium">Security notes</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
              <li>No registration (owner only)</li>
              <li>Token-based auth (Sanctum)</li>
              <li>Uploads stored privately</li>
            </ul>
          </div>
        </div>

        <Card>
          <template #header>
            <div class="text-lg font-semibold">Login</div>
            <div class="text-sm text-muted-foreground">
              Use your owner account
            </div>
          </template>

          <form
            v-if="!auth.twoStep.required"
            class="space-y-4"
            @submit.prevent="submit"
          >
            <div class="space-y-2">
              <Label>Email</Label>
              <Input
                v-model="email"
                type="email"
                placeholder="admin@johnrak.online"
              />
            </div>

            <div class="space-y-2">
              <Label>Password</Label>
              <Input
                v-model="password"
                type="password"
                placeholder="••••••••"
              />
            </div>

            <div
              v-if="auth.error"
              class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
              {{ auth.error }}
            </div>

            <Button class="w-full" type="submit" :disabled="auth.loading">
              {{ auth.loading ? "Signing in..." : "Sign in" }}
            </Button>

            <div class="text-xs text-muted-foreground">
              Tip: change your owner password in backend `.env` then re-run
              seeder.
            </div>
          </form>

          <form v-else class="space-y-4" @submit.prevent="submit2fa">
            <div class="space-y-2">
              <Label>Two-Factor Code</Label>
              <Input v-model="code" type="text" placeholder="123456" />
            </div>

            <div class="flex items-center justify-between">
              <label
                class="inline-flex items-center gap-2 text-sm text-muted-foreground"
              >
                <input
                  v-model="remember"
                  type="checkbox"
                  class="h-4 w-4 rounded border border-input bg-background"
                />
                Remember this device
              </label>
              <button
                type="button"
                class="text-sm text-muted-foreground hover:text-foreground"
                @click="useRecovery = !useRecovery"
              >
                {{ useRecovery ? "Use code instead" : "Use recovery code" }}
              </button>
            </div>

            <div v-if="useRecovery" class="space-y-2">
              <Label>Recovery Code</Label>
              <Input
                v-model="recovery"
                type="text"
                placeholder="XXXXXXXXXXXX"
              />
            </div>

            <div
              v-if="auth.error"
              class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
              {{ auth.error }}
            </div>

            <Button class="w-full" type="submit" :disabled="auth.loading">
              {{ auth.loading ? "Verifying..." : "Verify & Sign in" }}
            </Button>
          </form>
        </Card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import Card from "../components/ui/Card.vue";
import Input from "../components/ui/Input.vue";
import Label from "../components/ui/Label.vue";
import Button from "../components/ui/Button.vue";

const auth = useAuthStore();
const router = useRouter();

const email = ref("admin@johnrak.online");
const password = ref("ChangeThisPassword!");
const code = ref("");
const recovery = ref("");
const remember = ref(true);

async function submit() {
  const ok = await auth.login(email.value, password.value);
  if (ok === true) router.push("/");
}

async function submit2fa() {
  const ok = await auth.login2fa({
    code: useRecovery.value ? "" : code.value,
    recovery_code: useRecovery.value ? recovery.value : "",
    remember_device: remember.value,
  });
  if (ok) router.push("/");
}

const useRecovery = ref(false);
</script>
