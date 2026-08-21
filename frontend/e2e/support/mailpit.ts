/**
 * Client minimal pour l'API REST de Mailpit (docker-compose.e2e.yml, port 8025 publié en
 * local) - vérifié directement contre le code source de Mailpit (axllent/mailpit, branche
 * develop, internal/storage/structs.go et server/apiv1/messages.go) : les structs Go n'ont
 * aucun tag `json`, donc les clés JSON sont les noms de champ exacts ("ID", "To", "Text"...),
 * jamais une convention snake_case supposée. GET /api/v1/search?query=to:"<email>" pour
 * retrouver un message par destinataire (syntaxe "to:" confirmée dans
 * internal/storage/search.go), puis GET /api/v1/message/{ID} pour son corps texte complet.
 */
const MAILPIT_BASE_URL = process.env.MAILPIT_BASE_URL ?? "http://localhost:8025";

interface MailpitMessageSummary {
  ID: string;
  To: { Address: string }[];
  Subject: string;
  Created: string;
}

interface MailpitSearchResponse {
  messages: MailpitMessageSummary[];
}

interface MailpitMessage {
  Text: string;
}

async function searchMessagesTo(email: string): Promise<MailpitMessageSummary[]> {
  const query = encodeURIComponent(`to:"${email}"`);
  const response = await fetch(`${MAILPIT_BASE_URL}/api/v1/search?query=${query}`);
  if (!response.ok) {
    throw new Error(`Mailpit search failed: HTTP ${response.status}`);
  }
  const body = (await response.json()) as MailpitSearchResponse;
  return body.messages;
}

async function fetchMessageText(id: string): Promise<string> {
  const response = await fetch(`${MAILPIT_BASE_URL}/api/v1/message/${id}`);
  if (!response.ok) {
    throw new Error(`Mailpit fetch message failed: HTTP ${response.status}`);
  }
  const body = (await response.json()) as MailpitMessage;
  return body.Text;
}

/**
 * Attend (avec polling court) l'email envoyé à `email`, puis en extrait le premier lien HTTP
 * trouvé dans le corps texte - suffisant ici, chaque email de test (vérification, mot de
 * passe) n'en contient qu'un seul (App\Identity\Mailer\VerifyEmailMailer/PasswordResetMailer).
 */
export async function waitForEmailLink(email: string, timeoutMs = 15_000): Promise<string> {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const messages = await searchMessagesTo(email);
    if (messages.length > 0) {
      const newest = messages.reduce((latest, current) =>
        new Date(current.Created) > new Date(latest.Created) ? current : latest,
      );
      const text = await fetchMessageText(newest.ID);
      const match = /(https?:\/\/\S+)/.exec(text);
      if (match) {
        return match[1];
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }

  throw new Error(`No email with a link received for ${email} within ${timeoutMs}ms.`);
}
