"use client";

import { useState, type FormEvent } from "react";
import { HelpCircle } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { FormField } from "@/components/forms/FormField";
import { apiRequest, ApiError } from "@/lib/api/client";
import type { AssistantAnswer } from "@/lib/api/types";

type AskState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "answered"; question: string; answer: string }
  | { status: "error" }
  | { status: "email-required" };

/**
 * Question générale de compréhension (US-AI-002, docs/08-api-specification.md, section 35).
 * Volontairement délimitée visuellement du résultat de conformité lui-même
 * (docs/11-frontend-design-system.md, section 30 : "pour ne jamais laisser penser que la
 * question modifie le résultat affiché") -- rendue par la page appelante (InvoiceDetail)
 * après le résumé d'analyse, jamais à l'intérieur de ComplianceResultSummary.
 */
export function AssistantQuestionForm() {
  const [question, setQuestion] = useState("");
  const [state, setState] = useState<AskState>({ status: "idle" });

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if ("" === question.trim()) {
      return;
    }

    setState({ status: "loading" });

    try {
      const result = await apiRequest<AssistantAnswer>("/api/v1/assistant/questions", {
        method: "POST",
        body: { question },
      });
      setState({ status: "answered", question: result.question, answer: result.answer });
      setQuestion("");
    } catch (error) {
      if (error instanceof ApiError && error.code === "EMAIL_VERIFICATION_REQUIRED") {
        setState({ status: "email-required" });
        return;
      }
      setState({ status: "error" });
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-md border border-info/30 bg-info/5 p-4">
      <div className="flex items-center gap-2">
        <HelpCircle aria-hidden="true" size={16} className="text-info" />
        <h2 className="text-sm font-semibold text-foreground">Poser une question à l&apos;assistant</h2>
      </div>
      <p className="text-xs text-muted-foreground">
        Une question générale de compréhension (par exemple « qu&apos;est-ce qu&apos;un SIREN ? »). L&apos;assistant
        ne modifie jamais le résultat de conformité affiché ci-dessus.
      </p>

      <form onSubmit={handleSubmit} className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="flex-1">
          <FormField
            label="Votre question"
            name="question"
            maxLength={500}
            value={question}
            onChange={(event) => setQuestion(event.target.value)}
            disabled={state.status === "loading"}
          />
        </div>
        <Button type="submit" variant="secondary" loading={state.status === "loading"} className="w-fit">
          Demander
        </Button>
      </form>

      {state.status === "answered" ? (
        <div className="rounded-md border border-info/30 bg-surface px-3 py-2">
          <p className="text-xs font-medium text-info">Explication assistée</p>
          <p className="mt-1 text-xs text-muted-foreground">{state.question}</p>
          <p className="mt-1 text-sm text-foreground">{state.answer}</p>
        </div>
      ) : null}

      {state.status === "error" ? (
        <p role="alert" className="text-xs text-muted-foreground">
          L&apos;assistant n&apos;est pas disponible pour le moment. Réessayez dans quelques instants.
        </p>
      ) : null}

      {state.status === "email-required" ? (
        <p className="text-xs text-muted-foreground">
          Vérifiez votre adresse email pour utiliser l&apos;assistant.
        </p>
      ) : null}
    </div>
  );
}
