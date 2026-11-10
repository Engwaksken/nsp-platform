# AI writing assistant: design and review brief

Status: **built, switched off.** It cannot be switched on until Hive records legal approval (decision D13). This page is written for that review.

## What it does
Owners, founders and admins of a startup can ask for help with three tasks:

1. **Improve our startup description:** rewrites the founder's draft as a clearer public description.
2. **Tighten our funding pitch:** shortens the founder's pitch for investors.
3. **Suggest growth actions:** proposes 3 to 5 next steps for a goal the founder describes.

The answer is a **suggestion** shown once on screen. It is labelled "AI-generated" with the model name and time. NSP never saves it to the profile, pitch or plan; the founder copies what they want.

## Data sent to the selected AI provider
| Sent | Never sent |
|---|---|
| The text the founder types into the assistant box (up to 4,000 characters) | Documents and the data room |
| The startup's display name, sector and growth stage | Contact details, phone numbers, emails |
| A fixed instruction written by Hive | Revenue, funding amounts and other profile financials (unless the founder types them in the box) |
| | Notes, messages, applications, CVs, other people's data |

Automated tests check that none of the "never sent" profile fields reach the request (`AssistantTest`).

## What NSP stores
- **Consent:** who turned the assistant on, the wording version and when; withdrawal time.
- **Usage log (`ai_interactions`):** user, startup, task, model, outcome (ok, refused, error), character and token counts. **No prompt text and no answer text.**

## Safeguards
| Risk | Control |
|---|---|
| Used before approval | The feature flag can be switched on only when the selected provider has an API key **and** `NSP_AI_LEGAL_APPROVED=true` (or admin-recorded legal approval) is set. Withdrawing approval stops all use at once, even with the flag on. |
| Use without informed agreement | Each person opts in after reading what is sent and stored, and can turn it off at any time. Consent is versioned; a new wording version asks again. |
| Wrong people using it | Only owners, founders and admins of that startup; team viewers and outsiders cannot. |
| Prompt injection | Hive's instructions live only in the system prompt. The founder's text sits inside `<founder_text>` tags, and any closing tag inside it is removed. The model is told to treat that text as material, never as instructions. The model has **no tools**: it cannot fetch, send or change anything. Its answer is shown as escaped plain text. |
| Hallucinated facts | The instructions forbid inventing figures, customers or results. The screen tells the founder to check every suggestion, and nothing is saved automatically. |
| Harmful requests | The provider's safety classifiers may decline (shown as "could not help with this request"). Server-side fallback routing is on. |
| Cost and abuse | Limits per person (20 a day), per startup (50) and platform-wide (500), plus 6 requests a minute. All are configurable in `.env`. |
| Outages | Errors become a plain message. The Anthropic SDK retries transient failures; other adapters use bounded HTTPS requests. |

## Configuration
Admins can select Anthropic, OpenAI, Google Gemini, or an OpenAI-compatible custom HTTPS endpoint, choose a supported model (or enter a custom model ID for the custom endpoint), save an encrypted API key, and test access to the selected model under **Admin → AI assistant**. Only one provider/model is active at a time. Custom endpoints are restricted to public HTTPS hosts and do not follow redirects. Custom provider compatibility and prices are not guaranteed; test them before enabling the assistant. API keys are encrypted in the database; the optional Anthropic environment fallback is `ANTHROPIC_API_KEY`. Other settings include `NSP_AI_ASSISTANT_ENABLED`, `NSP_AI_LEGAL_APPROVED`, `NSP_AI_MODEL`, `NSP_AI_EFFORT`, `NSP_AI_DAILY_PER_USER`, `NSP_AI_DAILY_PER_STARTUP` and `NSP_AI_DAILY_TOTAL`.

## Questions for the legal reviewer
1. Is Hive's agreement with each provider admins may enable (data processing, retention, use for training) acceptable for founders' business text under Uganda's Data Protection and Privacy Act and the other countries served?
2. Is the consent wording on the assistant page enough, or should the privacy notice also change?
3. Are the three tasks acceptable, or should any be removed (for example pitch help, given financial-promotion rules)?
4. Should use be limited further, for example to verified startups only?
