"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useRef, useState, type FormEvent } from "react";
import { Button } from "@/components/ui/Button";
import { FormField } from "@/components/forms/FormField";
import { useAuth } from "@/components/auth/AuthProvider";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

export default function RegisterPage() {
  const { register } = useAuth();
  const router = useRouter();
  const emailRef = useRef<HTMLInputElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!termsAccepted) {
      return;
    }
    setLoading(true);
    setErrors(EMPTY_ERRORS);

    try {
      await register(email, password);
      router.push("/login?registered=1");
    } catch (error) {
      const formErrors = toFormErrors(error, "Impossible de créer le compte, veuillez réessayer.");
      setErrors(formErrors);
      // Focus déplacé vers le premier champ en erreur (design system, section 45).
      if (formErrors.fieldErrors.email) {
        emailRef.current?.focus();
      } else if (formErrors.fieldErrors.password) {
        passwordRef.current?.focus();
      }
      setLoading(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1 text-center">
        <h1 className="text-xl font-semibold text-foreground">Créer un compte</h1>
        <p className="text-sm text-muted-foreground">
          Commencez à préparer votre conformité à la facturation électronique.
        </p>
      </div>

      {errors.formError ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {errors.formError}
        </p>
      ) : null}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
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
          ref={passwordRef}
          label="Mot de passe"
          type="password"
          name="password"
          autoComplete="new-password"
          minLength={15}
          required
          hint="15 caractères minimum."
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={errors.fieldErrors.password}
        />

        <div className="flex items-start gap-2">
          <input
            id="terms-accepted"
            type="checkbox"
            required
            checked={termsAccepted}
            onChange={(event) => setTermsAccepted(event.target.checked)}
            className="mt-0.5 h-4 w-4 shrink-0 rounded border-border text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
          />
          <label htmlFor="terms-accepted" className="text-sm text-muted-foreground">
            J&apos;accepte les{" "}
            <Link href="/cgu" target="_blank" className="font-medium text-primary hover:underline">
              conditions générales d&apos;utilisation
            </Link>{" "}
            et la{" "}
            <Link href="/politique-de-confidentialite" target="_blank" className="font-medium text-primary hover:underline">
              politique de confidentialité
            </Link>
            .
          </label>
        </div>

        <Button type="submit" loading={loading} disabled={!termsAccepted}>
          Créer mon compte
        </Button>
      </form>

      <p className="text-center text-sm text-muted-foreground">
        Déjà un compte ?{" "}
        <Link href="/login" className="font-medium text-primary hover:underline">
          Se connecter
        </Link>
      </p>
    </div>
  );
}
