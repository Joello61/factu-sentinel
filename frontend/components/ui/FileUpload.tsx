"use client";

import { Upload } from "lucide-react";
import { useId, useRef, useState, type DragEvent } from "react";

const MAX_SIZE_BYTES = 20 * 1024 * 1024;
const ACCEPTED_EXTENSIONS = ".pdf,.xml";

interface FileUploadProps {
  onFileSelected: (file: File) => void;
  disabled?: boolean;
  error?: string | null;
}

/**
 * Zone de dépôt de fichier (docs/11-frontend-design-system.md, sections 36, 55, 593) : zone
 * neutre, surbrillance au survol d'un fichier glissé, focus clavier visible, état désactivé,
 * message d'erreur explicite sur format/taille invalide. Première occurrence d'un composant
 * d'upload dans ce projet (Phase 7).
 *
 * Pas de barre de progression en pourcentage ici (contrairement à la mention de la section
 * 36) : `fetch()` n'expose pas nativement la progression d'upload sans remplacer tout le
 * client API par XMLHttpRequest pour ce seul cas d'usage - simplification volontaire pour
 * cette phase (état "en cours"/désactivé suffisant à 20 Mo maximum par fichier), à
 * reconsidérer si des uploads plus volumineux devenaient courants.
 *
 * La validation réelle (taille, magic bytes) reste toujours celle du backend
 * (App\Document\Service\UploadedDocumentValidator) - cette validation cliente n'est qu'un
 * confort, jamais la source de vérité (../../CLAUDE.md frontend, section 15).
 */
export function FileUpload({ onFileSelected, disabled = false, error = null }: FileUploadProps) {
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragActive, setDragActive] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  function validateAndSelect(file: File) {
    if (file.size > MAX_SIZE_BYTES) {
      setLocalError("Le fichier dépasse la taille maximale autorisée (20 Mo).");
      return;
    }
    setLocalError(null);
    onFileSelected(file);
  }

  function handleDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setDragActive(false);
    if (disabled) {
      return;
    }
    const file = event.dataTransfer.files[0];
    if (file) {
      validateAndSelect(file);
    }
  }

  const displayedError = error ?? localError;

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={inputId} className="text-sm font-medium text-foreground">
        Importer un document (PDF ou Factur-X)
      </label>
      <div
        onDragOver={(event) => {
          event.preventDefault();
          if (!disabled) {
            setDragActive(true);
          }
        }}
        onDragLeave={() => setDragActive(false)}
        onDrop={handleDrop}
        className={`flex flex-col items-center gap-2 rounded-md border-2 border-dashed px-4 py-8 text-center transition-colors ${
          disabled
            ? "cursor-not-allowed border-border bg-surface opacity-60"
            : dragActive
              ? "border-primary bg-primary/10"
              : "border-border hover:bg-primary/5"
        }`}
      >
        <Upload aria-hidden="true" size={24} className="text-muted-foreground" />
        <p className="text-sm text-muted-foreground">
          Glissez-déposez un fichier ici, ou{" "}
          <button
            type="button"
            disabled={disabled}
            onClick={() => inputRef.current?.click()}
            className="font-medium text-primary underline-offset-2 hover:underline disabled:cursor-not-allowed"
          >
            parcourir
          </button>
        </p>
        <p className="text-xs text-muted-foreground">PDF ou XML (Factur-X), 20 Mo maximum</p>
        <input
          ref={inputRef}
          id={inputId}
          type="file"
          accept={ACCEPTED_EXTENSIONS}
          disabled={disabled}
          className="sr-only"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file) {
              validateAndSelect(file);
            }
            event.target.value = "";
          }}
        />
      </div>
      {displayedError ? (
        <p role="alert" className="text-xs text-error">
          {displayedError}
        </p>
      ) : null}
    </div>
  );
}
