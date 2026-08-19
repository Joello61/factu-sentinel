'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { FormField } from '@/components/forms/FormField';
import { useAuth } from '@/components/auth/AuthProvider';
import { toFormErrors, type FormErrors } from '@/lib/forms/api-error';

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

export function LoginForm({ justRegistered }: { justRegistered: boolean }) {
  const { login } = useAuth();
  const router = useRouter();
  const emailRef = useRef<HTMLInputElement>(null);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setErrors(EMPTY_ERRORS);

    try {
      await login(email, password);
      router.push('/');
    } catch (error) {
      // US-AUTH-002 : message volontairement non spécifique, jamais "email inconnu" ni
      // "mot de passe incorrect" - cohérent avec le message générique déjà renvoyé par le
      // backend (AuthFailureEnvelopeListener).
      const formErrors = toFormErrors(error, 'Identifiants invalides.');
      setErrors(formErrors);
      emailRef.current?.focus();
      setLoading(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1 text-center">
        <h1 className="text-xl font-semibold text-foreground">Connexion</h1>
        <p className="text-sm text-muted-foreground">
          Accédez à votre espace FactuSentinel.
        </p>
      </div>

      {justRegistered ? (
        <p className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
          Compte créé. Vous pouvez maintenant vous connecter.
        </p>
      ) : null}

      {errors.formError ? (
        <p
          role="alert"
          className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error"
        >
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
          Se connecter
        </Button>
      </form>

      <p className="text-center text-sm text-muted-foreground">
        Pas encore de compte ?{' '}
        <Link
          href="/register"
          className="font-medium text-primary hover:underline"
        >
          Créer un compte
        </Link>
      </p>
    </div>
  );
}
