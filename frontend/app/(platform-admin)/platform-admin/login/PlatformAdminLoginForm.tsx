"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ShieldAlert } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { FormField } from "@/components/forms/FormField";
import { usePlatformAdminAuth } from "@/components/platform-admin/PlatformAdminAuthProvider";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

/**
 * Connexion en deux étapes (plan Phase 15, docs/10-security-privacy.md section 17 bis : MFA
 * obligatoire sans exception). Aucun raccourci : le formulaire de code TOTP n'apparaît
 * qu'après un login réussi (status "mfa_required"), jamais avant.
 */
export function PlatformAdminLoginForm() {
  const { status, login, verifyMfa, cancelMfa } = usePlatformAdminAuth();
  const router = useRouter();
  const emailRef = useRef<HTMLInputElement>(null);
  const codeRef = useRef<HTMLInputElement>(null);

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);

  useEffect(() => {
    if (status === "authenticated") {
      router.replace("/platform-admin/organizations");
    }
  }, [status, router]);

  useEffect(() => {
    if (status === "mfa_required") {
      codeRef.current?.focus();
    }
  }, [status]);

  async function handleLoginSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setErrors(EMPTY_ERRORS);
    try {
      await login(email, password);
    } catch (error) {
      setErrors(toFormErrors(error, "Identifiants invalides."));
      emailRef.current?.focus();
    } finally {
      setLoading(false);
    }
  }

  async function handleMfaSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setErrors(EMPTY_ERRORS);
    try {
      await verifyMfa(code);
    } catch (error) {
      setErrors(toFormErrors(error, "Code invalide."));
      setCode("");
      codeRef.current?.focus();
    } finally {
      setLoading(false);
    }
  }

  if (status === "mfa_required") {
    return (
      <div className="flex flex-col gap-6">
        <div className="flex flex-col gap-1 text-center">
          <h1 className="text-xl font-semibold text-foreground">Vérification en deux étapes</h1>
          <p className="text-sm text-muted-foreground">
            Saisissez le code à 6 chiffres de votre application d&apos;authentification.
          </p>
        </div>

        {errors.formError ? (
          <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
            {errors.formError}
          </p>
        ) : null}

        <form onSubmit={handleMfaSubmit} noValidate className="flex flex-col gap-4">
          <FormField
            ref={codeRef}
            label="Code de vérification"
            name="code"
            inputMode="numeric"
            autoComplete="one-time-code"
            required
            maxLength={6}
            value={code}
            onChange={(event) => setCode(event.target.value.replace(/\D/g, ""))}
            error={errors.fieldErrors.code}
          />
          <Button type="submit" loading={loading}>
            Vérifier
          </Button>
          <Button
            type="button"
            variant="secondary"
            onClick={() => {
              cancelMfa();
              setPassword("");
              setCode("");
              setErrors(EMPTY_ERRORS);
            }}
          >
            Annuler
          </Button>
        </form>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1 text-center">
        <ShieldAlert aria-hidden="true" className="mx-auto text-primary" size={28} strokeWidth={1.75} />
        <h1 className="text-xl font-semibold text-foreground">Administration plateforme</h1>
        <p className="text-sm text-muted-foreground">Accès réservé aux administrateurs de FactuSentinel.</p>
      </div>

      {errors.formError ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {errors.formError}
        </p>
      ) : null}

      <form onSubmit={handleLoginSubmit} noValidate className="flex flex-col gap-4">
        <FormField
          ref={emailRef}
          label="Adresse email"
          type="email"
          name="email"
          autoComplete="email"
          required
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          error={errors.fieldErrors.email}
        />
        <FormField
          label="Mot de passe"
          type="password"
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={errors.fieldErrors.password}
        />
        <Button type="submit" loading={loading}>
          Continuer
        </Button>
      </form>
    </div>
  );
}
