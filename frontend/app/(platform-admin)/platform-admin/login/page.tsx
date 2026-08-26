import { PlatformAdminLoginForm } from "./PlatformAdminLoginForm";

export default function PlatformAdminLoginPage() {
  return (
    <main className="flex min-h-full flex-1 items-center justify-center bg-surface p-6">
      <div className="w-full max-w-sm">
        <PlatformAdminLoginForm />
      </div>
    </main>
  );
}
