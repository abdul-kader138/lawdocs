---
title: LawDocs User Manual
subtitle: Professional operating guide
date: Version 1.2 · 13 August 2026
---

> **Purpose.** LawDocs is a staff-facing legal-document assembly system. It combines an approved Word precedent with client information, questionnaire answers, and party details to produce a draft DOCX and, where available, a PDF. It records review, signing, and witness activity; it does not provide legal advice, verify drafting, or send documents to an e-signature provider.

\newpage

## 1. Before you begin

### 1.1 What you need

- A LawDocs account and the URL supplied by your administrator (the standard panel path is `/admin`).
- A modern web browser.
- An authenticator app if two-factor authentication (2FA) is enabled for your account.
- Accurate client instructions and authority to handle the personal information entered.

### 1.2 Roles and screen access

| Role | Normal access |
|---|---|
| **panel_user** | Dashboard; personal Documents drive; view/create document requests; view/create/update clients and contacts. Cannot delete clients or manage precedents. |
| **operator** | All panel-user functions; personal Documents drive; full client management; precedent management; review/approval actions when permitted. |
| **super_admin** | Full access, including all users' Documents drives, Audit Log, staff accounts, and System Settings. |

An administrator can restrict an operator to selected precedent categories. Menus and buttons the user lacks permission for are hidden. Your screen may therefore contain fewer items than this manual.

### 1.3 Important safeguards

1. Treat every generated document as a **draft** until reviewed under the firm's procedure.
2. A green **Completed** status means generation succeeded; it does not mean the document is legally correct.
3. **Approve for Download** records human approval but performs no automatic legal check.
4. Minimum-age and witness messages are guidance only. Confirm jurisdiction-specific requirements with a solicitor.
5. **Send for Signature** is a tracking action only. Printing or e-signing occurs outside LawDocs.

## 2. Screen map and navigation

After sign-in, the left navigation shows the areas available to your role:

| Screen | Purpose | Main controls |
|---|---|---|
| **Dashboard** | Workload summary and recent activity | Time-range filter on chart; recent requests |
| **Document Requests** | Create, monitor, approve, download, and track documents | New Document Request, filters, row actions, View |
| **Clients** | Reusable client and related-contact records | New Client, View, Edit, Delete (permission dependent) |
| **Documents** | Private working-file manager for each user | New folder/file, upload, download, rename, copy, move, delete |
| **Precedents** | Configure templates and dynamic questionnaires | New Precedent, Edit, Active toggle (operator/admin) |
| **Users** | Staff accounts, roles, and category restrictions | New User, Edit, Delete (admin) |
| **Administration → Audit Log** | Read-only history of security and record activity | Search, Area/Event/Date filters, View (admin permission) |
| **System Settings** | Branding, output defaults, security, and email | Tabs and Save (admin) |
| **Profile menu** | Your details, password, avatar, and 2FA | Profile, notifications, Sign out |

Common table controls include search, sortable column headings, filters, column visibility, pagination, and per-row actions. A dash (`—`) means no value or that the item is not applicable.

## 3. Sign in and account security

### Screen: Sign in

1. Open the LawDocs `/admin` address.
2. Enter **Email address** and **Password**.
3. Select **Remember me** only on a private, trusted device.
4. Select **Sign in**.
5. If the account is protected by 2FA, the screen changes to **Two-factor authentication**. Enter either the current six-digit authenticator code or one unused recovery code, then select **Verify**.
6. Use **Back to login** if you need to change the email/password entry.

After five rapid unsuccessful authentication attempts, sign-in is temporarily rate-limited. Wait for the displayed period and try again. Do not repeatedly guess credentials.

### Screen: Profile

Open the account menu at the top right and select **Profile**.

1. In **Profile Picture**, upload an image; use the image editor/circular crop if desired.
2. In **Personal Information**, update your name or email.
3. In **Change Password**, enter and confirm a new password. Leave both password fields blank to keep the existing password.
4. Select **Save changes**.

### Enable two-factor authentication

If the firm-wide 2FA switch is on:

1. On Profile, select **Enable Two-Factor Authentication**.
2. Scan the QR code with Microsoft Authenticator, Google Authenticator, 1Password, or another TOTP app. The text secret is available for manual entry.
3. Enter the current six-digit code from the app.
4. Confirm the setup.
5. Select **Show Recovery Codes** and store the codes in an approved secure location. Each recovery code is single-use.

Use **Regenerate Recovery Codes** if the set may be exposed; existing codes immediately stop working. Use **Disable Two-Factor Authentication** only in accordance with firm policy. If the administrator disables 2FA firm-wide, setup controls and login challenges are hidden even for previously enrolled accounts.

## 4. Dashboard

### Screen: Dashboard

The welcome panel shows the signed-in user and a summary of today's work. Four statistic cards show:

- **In Progress** — requests in Pending or Processing.
- **Completed** — drafts generated successfully.
- **Failed** — requests with a generation error.
- **Awaiting Review** — completed drafts whose precedents require approval and have not yet been approved.

The **Document Requests** stacked chart groups Pending, Processing, Completed, and Failed items by date. Choose **Last 7 days**, **Last 14 days**, or **Last 30 days**. The **Recent Document Requests** table lists up to eight recent items with precedent, requester, status, and relative time. Dashboard figures refresh approximately every 30 seconds.

Use the Dashboard to identify urgent failed generations and drafts awaiting review; open **Document Requests** for the operational actions.

## 5. Clients and contacts

Creating a client once allows their details to prefill future requests when a precedent has client mapping configured. Client notes are internal and never inserted into a generated document.

### Screen: Clients list

The list shows Name, Email, Phone, number of Contacts, and number of Documents. Search by name/email, sort a column, or use the row actions:

- **View** — open the client and contacts.
- **Edit** — correct the record.
- **Delete** — available only with delete permission. Confirm carefully; related contacts are removed and existing document history may retain a blank client link.

### Screen: New Client / Edit Client

1. Select **New Client** (or **Edit** on an existing row).
2. Under **Identity**, enter **Full Name** (required). Add Gender, Date of Birth, Email, and Phone where known. Gender is used for pronoun agreement when mapped to a document.
3. Under **Address**, enter Street, Suburb, Australian State/Territory, and Postcode.
4. Under **Notes**, add internal context only. Do not place wording here that is expected to appear in the document.
5. Select **Create** or **Save changes**.

The document wizard also permits creating a minimal client directly from the Client selector using name, email, and phone. For a complete record, create it from the Clients screen first.

### Screen: View Client — Contacts section

Contacts are reusable people associated with the client, such as a spouse, child, accountant, proposed attorney, beneficiary, or guardian.

1. Open a client and locate **Contacts**.
2. Select **Create**.
3. Enter **Full Name** (required) and, where available, Relationship to Client, Gender, Date of Birth, Email, Phone, address, and Notes.
4. Select **Create**.
5. Use the row **Edit** or **Delete** actions to maintain the contact.

During document creation, contacts can be imported into compatible party groups. Matching fields such as name, relationship, gender, email, phone, address, and date of birth are copied; unmatched fields such as an estate share remain for manual completion.

## 6. Documents file manager

### Purpose and access

Select **Documents** in the navigation to open the private file manager. It is a working area for organising files and folders separately from generated Document Request downloads, signed-copy attachments, and precedent templates.

Every normal user is restricted to their own document drive. A super administrator sees **Viewing documents for** and may choose **My files** or another user's name/email to inspect and manage that user's drive. Administrator actions are performed on the selected owner's files, so confirm the selected user before uploading, moving, renaming, or deleting anything.

The screen loads folders and files on demand and displays storage usage against the owner's configured quota. The installation defaults are 2 GB per owner and 100 MB for one upload, but administrators may configure different limits; rely on the values/errors shown by the deployed system.

### Understand the file-manager screen

The exact toolbar/context-menu layout can vary with screen width, but the available operations are:

| Operation | What it does | Important effect |
|---|---|---|
| **New folder** | Creates an organisational folder in the current location | Duplicate names receive a numbered suffix |
| **New file** | Creates a zero-byte placeholder file | It is not an online Word/text editor |
| **Upload** | Copies a local file into the current folder | Counts against owner quota and upload-size limit |
| **Download** | Downloads the selected stored file | Folders cannot be downloaded as files |
| **Rename** | Changes the file/folder's visible name/path | Folder descendants move with the renamed path |
| **Move** | Relocates selected items to another folder | A folder cannot be moved into itself/its descendant |
| **Copy** | Duplicates selected files/folders and their contents | Copies use additional quota; collisions get numbered names |
| **Delete** | Removes selected files/folders | Folder deletion includes descendants and is not a recycle-bin action |

Use the toolbar, item context menu, or standard file-manager selection gestures exposed by the screen. Wait for an operation to finish before refreshing or repeating it.

### Create a folder structure

1. Open **Documents**.
2. Administrators: confirm **Viewing documents for** is the intended owner.
3. Navigate to the parent location where the folder belongs.
4. Choose the new-folder action.
5. Enter a clear name such as `SUL-2026-014` or `Current Precedents`.
6. Confirm creation, then open the folder.

Recommended structure example:

```text
Client Matters
└── SUL-2026-014
    ├── Instructions
    ├── Drafts
    └── Executed
```

Use the firm's approved naming convention. Do not include `/` in names; LawDocs removes path separators. If a name already exists, LawDocs creates a unique version such as `Draft (1).docx` rather than overwriting the existing item.

### Upload a file

1. Open the destination folder before starting the upload.
2. Choose **Upload**.
3. Select the authorised local file.
4. Retain or enter the intended filename.
5. Confirm the upload and wait for the item to appear.
6. Check the name, folder, file size, and storage-usage indicator.
7. Download and open critical uploads once to confirm the correct file was stored.

If the file exceeds the single-upload limit or would exceed the owner's quota, the upload is rejected. Remove only genuinely redundant data under retention policy or ask an administrator to review capacity. Do not split sensitive documents across unapproved services.

### Download a file

1. Locate and select the file—not a folder.
2. Choose **Download**.
3. The browser opens/downloads the file in a new tab or according to browser settings.
4. Store the downloaded copy only in an approved location and remove uncontrolled temporary copies when finished.

### Rename, move, or copy

**Rename:** select the item, choose Rename, enter the complete new name including the correct extension where applicable, and confirm. Renaming `draft.docx` to `draft.pdf` changes only the name, not the file format.

**Move:** select one or more items, choose Move, select the destination, and confirm. Moving a folder moves its entire subtree. Verify the destination afterwards.

**Copy:** select items, choose Copy, select the destination, and confirm. Files and all folder descendants are physically duplicated and count against quota. When a target name exists, LawDocs generates a unique numbered name.

### Delete files or folders

1. Confirm the selected owner, path, and exact items.
2. Check the firm's retention requirements and whether another record depends on the file.
3. Select **Delete** and confirm.
4. If deleting a folder, understand that every child file/folder is removed too.

Deletion removes both the file-manager record and stored file; this screen has no recycle bin or restore action. Recovery, if possible at all, depends on separately managed backups. File creation, changes, and deletion are recorded in the Audit Log.

### File-manager troubleshooting

| Symptom | Likely cause | Action |
|---|---|---|
| Could not load documents | Session, server, storage, or network problem | Refresh once; if repeated, report user, time, and screen error |
| Upload too large | File exceeds configured maximum | Use an approved smaller file or ask the administrator; do not bypass controls |
| Storage quota exceeded | Owner's stored file total is at limit | Review retention/capacity with an administrator |
| Expected file name has `(1)` | Same name already existed | Compare files and rename deliberately; LawDocs avoided overwriting |
| Cannot download | Selected item is a folder, missing, or storage unavailable | Select a file; report persistent failures |
| Administrator sees wrong files | Wrong owner selected | Stop and choose the correct user before making changes |

## 7. Create a document request — complete walkthrough

### Screen: Document Requests list

The table shows Precedent, Client, Requested By, Status, Approval, Case Reference, and Requested date/time. Use the Client filter to narrow the list. Select **New Document Request** to begin.

### Step 1 of 3: Precedent

1. In **Client (optional)**, search for and select an existing client. This enables mapped prefilling and contact imports. You can leave it blank for an ad hoc matter.
2. If the client is missing, use the create option in the selector for a minimal record, or cancel and create the full client from **Clients**.
3. In **Precedent**, search for and choose the required document template. Only active precedents available to your role appear.
4. Confirm the title and, through firm procedure, the correct jurisdiction/document type. Each jurisdiction is maintained as a separate precedent.
5. Select **Next**.

Changing the precedent resets its dynamic answers and party groups before applying any configured client prefill. Recheck every field after changing either selector.

#### How the selected precedent controls the request

The Precedent selection is not merely a file choice. It controls four connected parts of the request:

| Precedent configuration | What staff see or receive |
|---|---|
| Questionnaire Fields | The individual inputs on the Details step, such as Principal's Full Name or Enduring Power of Attorney? |
| Client Mapping | Which questionnaire inputs are prefilled from the selected client |
| Party Groups | Repeatable rows such as Beneficiaries, Attorneys, or Guardians, including minimum rows and share validation |
| Generator + Template File | The clauses, conditional wording, repeated party wording, formatting, and final document title |

If an expected field or party group is missing from Details, stop. Do not force the information into an unrelated field. The precedent configuration must be corrected by an authorised operator before a reliable document can be generated.

### Step 2 of 3: Details

The fields are generated from the selected precedent, so this screen varies. Required fields are marked. Inputs can be text, multi-line text, number, date, Yes/No, or a fixed-choice list.

1. Review all client-prefilled values against current instructions. Prefill is editable and is not proof that the information remains current.
2. Complete each questionnaire field. Enter names exactly as they should appear in the legal document.
3. For each party group, either:
   - select people in **Import into [Group] from [Client]'s Contacts**, then complete any missing values; or
   - select **Add [Group]** and enter the party manually.
4. Expand/collapse rows as needed and drag to reorder them. Order can affect the generated document.
5. For beneficiary groups, make all shares total exactly **100%**. Submission is rejected otherwise.
6. If **Per stirpes substitution** appears, enable it only where the instructions require the person's share to pass to their surviving children if that person does not survive.
7. If **Specific Substitute** appears, choose the intended replacement from another row. Avoid duplicate party names because substitute matching uses the displayed primary name.
8. Select **Next**.

#### Worked request example: Power of Attorney

Assume client **Amara Osei** is selected with a Power of Attorney precedent whose client mapping connects `Client name → principal_name` and `Client DOB → principal_dob`.

1. The Details screen prefills Principal's Full Name as **Amara Osei** and the saved date of birth. Confirm both against current identification.
2. Enter Principal's Address as **5 Bridge Road, Manly NSW 2095**.
3. Set **Enduring Power of Attorney?** to Yes if the approved instructions require the enduring clause.
4. Set **Attorneys must act jointly?** to No if the attorneys may act jointly and severally.
5. Under Attorneys, add **Kwame Osei**, his address, and relationship **Son**.
6. Add **Linda Park**, her address, and relationship **Friend**.
7. Preserve the intended attorney order and continue to Review.

With those values, a correctly configured template can repeat the appointment sentence once for each attorney, include the enduring notice, and choose the jointly-and-severally wording. The request screen supplies data; it does not decide whether those instructions are legally suitable.

#### Worked request example: Will beneficiary validation

For two beneficiaries, a valid setup could be:

| Order | Name | Share | Gender | Per stirpes |
|---|---|---:|---|---|
| 1 | David Sullivan | 60 | Male | Yes |
| 2 | Emily Sullivan | 40 | Female | No |

The total is 100%, so validation passes. The beneficiary clause repeats twice. The per-stirpes subclause appears only below David's gift because his toggle is Yes. Entries of 60% and 30%, blank shares, or an accidental duplicate row prevent submission or produce incorrect instructions and must be corrected.

#### Request-entry quality checks

Before leaving Details, confirm:

- names, accents, initials, and entity suffixes are exactly as intended;
- address text is complete and formatted for insertion into the document;
- all Yes/No answers match the signed instructions;
- no imported contact was added twice;
- row order reflects appointment/gift priority;
- each required minimum party count is satisfied;
- percentage shares total 100 where enforced;
- gender selections are correct because generators may use them for pronouns;
- optional fields are intentionally blank, not accidentally overlooked.

### Included starter precedents and their data

The repository provides these sample NSW precedents. A live installation may have different or additional active precedents.

**Last Will and Testament** captures testator name, optional date of birth, street, suburb/city, state wording, gender, executor name/gender, optional alternate executor name/gender, and one or more beneficiaries. Each beneficiary has name, estate share, gender, and optional per-stirpes treatment. Shares must total 100%.

**Power of Attorney** captures principal name, optional date of birth, address, whether the power is enduring, whether multiple attorneys act jointly, and one or more attorneys with name, address, and relationship.

**Enduring Guardianship** captures principal name, optional date of birth, address, whether multiple guardians act jointly, and one or more guardians with name, address, and relationship.

These starter templates are explicitly demo content and require solicitor review before real-client use.

### Step 3 of 3: Review

1. Enter **Case / Matter Reference** if your filing convention uses one. It is optional but strongly recommended for retrieval.
2. Read any age warning. An entered testator/principal date of birth below the ordinary minimum age produces a soft warning; it does not block submission because exceptions may exist.
3. Return to **Details** and perform a final comparison with signed-off client instructions.
4. Select **Create** / submit the wizard once. Generation normally takes about 10–30 seconds when synchronous processing is configured.

The new request stores a snapshot of the precedent title, jurisdiction, answers, parties, requester, and time. It then moves through these statuses:

| Status | Meaning | User response |
|---|---|---|
| **Pending** | Accepted but not yet started | Wait; if it remains pending unusually long, ask an administrator to check the queue worker. |
| **Processing** | Draft is being assembled | Wait and refresh later; do not submit duplicates. |
| **Completed** | DOCX was generated | Review/approve if required, then download. |
| **Failed** | Generation could not finish | Open View, read Error, correct the precedent/input with an authorised person, then Regenerate. |

The notification bell is polled about every 30 seconds for completion/failure notifications.

### What happens after submission

Submission saves the questionnaire answers separately from party rows. The generator then reads the selected precedent and combines:

1. generator-produced paragraphs, such as the Will executor appointment chain;
2. verbatim tagged clauses from the uploaded DOCX;
3. conditional blocks selected by Yes/No answers or computed flags;
4. repeated blocks expanded once per stored party row; and
5. placeholders replaced with request values.

This explains why a request can be entered correctly but still fail when the precedent contains an unknown placeholder, a missing required clause, or a mismatched group key. Open the failed request and preserve its exact Error message for the precedent administrator.

## 8. Review, approval, and download

### Screen: Document Request view

Open **View** from the list. The **Request** section displays precedent, client, requester, case reference, generation status/error, generated title/time, approval status, and approver. **Submitted Answers** preserves the values used for generation.

Use the following review procedure:

1. Compare **Submitted Answers** with source instructions.
2. If permitted and the draft needs review, download or otherwise inspect it according to the firm's controlled review process. The normal download buttons remain locked until approval for review-required precedents.
3. A user with approval permission selects **Approve for Download** (or **Approve** on the list) and confirms. This records the approver and timestamp.
4. Select **Download .docx** for the editable Word draft.
5. Select **Download PDF** for a PDF rendition when the server's office converter is available. If the button is absent, download DOCX and follow the firm's approved conversion method.

Download is available only when generation is Completed and either review is not required or approval has been recorded. A direct download link cannot bypass this rule.

### Regenerate an existing request

Use **Regenerate** when a corrected version is required.

1. Open the existing request and select **Regenerate**.
2. A new three-step request is opened with the existing client, precedent, answers, parties, and case reference prefilled where still valid.
3. Verify all values—especially if the precedent has since changed.
4. Correct the required data and submit.
5. Treat the result as a new request with its own generation and approval history. The original remains available as an audit record.

## 9. Signature and witness tracking

The **Signing** section appears after the request is ready for download. Its status is **Not sent**, **Sent for signature**, or **Signed**.

### Record sending

1. Complete approval/download checks.
2. Send the document for wet signature or external e-signature using the firm's normal process.
3. In LawDocs select **Send for Signature** and confirm. This only saves the sent timestamp; it does not transmit the document.

### Record witnesses

1. On the request view, locate the **Witnesses** table.
2. Select **Create**.
3. Enter Full Name (required), Address, and Occupation.
4. Save. Add further witnesses as required.
5. Compare the recorded count with the displayed jurisdiction guidance and confirm the actual legal requirement with a solicitor.

LawDocs prevents a person already recorded as a document party from being entered as a witness. Use **Edit** or **Delete** to correct witness records.

### Mark signed and attach the executed copy

1. Once signing is confirmed, select **Mark as Signed**.
2. Optionally upload the wet-signed scan or externally e-signed copy under **Executed Document**.
3. Confirm. The signed timestamp is recorded.
4. If a file was attached, **Download Signed Copy** becomes available.

## 10. Precedent management (operator/administrator)

Precedent changes affect future generations and require controlled testing and solicitor approval. Existing request snapshots remain historical records.

### Screen: Precedents list

The list includes Title, Category, Jurisdiction, Active, Generator, questionnaire-field count, and creation date. Search/filter as available. The **Active** switch controls whether staff can select the precedent in a new request.

### Screen: New/Edit Precedent — Details tab

1. Enter Title, Category, and Jurisdiction.
2. Choose the **Document Generator** that matches the document type. A developer must add new generator types.
3. Add a clear Description.
4. Set **Active** only after validation and approval.
5. Leave **Requires review before download** on unless the firm has formally approved unreviewed release.

### Template File tab

The Template File is the most technically sensitive part of precedent setup. Build and test it in a non-production workflow. Only `.docx` files are accepted.

#### First understand what this Word file is—and is not

For the four included generators, LawDocs does **not** use the uploaded Word file as one complete mail-merge document. It reads only the content placed between recognised `[[CLAUSE:...]]` and `[[/CLAUSE]]` boundaries. The generator then places those named clause contents into a newly generated document together with headings and paragraphs produced by the application.

This has four important consequences for a beginner:

1. Ordinary text typed outside a named clause is not automatically included in the final document.
2. A clause name is a connection point between Word and the selected generator. It is not a heading visible to the client.
3. The generator must request that exact clause name. A correctly spelled but unused clause will still not appear.
4. The Word file supplies approved clause wording and formatting; the Questionnaire Fields and Party Groups supply variable data.

Think of the process as:

```text
Questionnaire answers + Party rows + Named Word clauses
                         ↓
                 Selected generator
                         ↓
                 New generated document
```

#### Do not begin in Word until these items are ready

Before preparing the DOCX, complete or confirm:

1. The solicitor-approved clause wording for the correct jurisdiction.
2. The correct **Document Generator** on the Details tab.
3. The complete **Questionnaire Fields** list and exact field names.
4. The complete **Party Groups** list, group keys, and row field names.
5. Which passages are always included, conditional, or repeated.
6. A test plan containing realistic but non-client data.

If any technical name changes after the DOCX is built, every reference to that name must also change.

#### Exact clause names required by the included generators

Use this table only for the included generator selected on the Details tab. Custom generators may require different names.

| Selected generator | Clause markers required by the generator | Allowed repeat group | Allowed IF conditions |
|---|---|---|---|
| **Will** | `revocation`, `beneficiaries_clause`, `executor_powers` | `beneficiaries` | `beneficiary.per_stirpes` inside the beneficiary repeat |
| **Power of Attorney** | `appointment_clause`, `enduring_notice`, `general_powers`, `revocation` | `attorneys` | `attorneys_act_jointly`, `is_enduring` |
| **Enduring Guardianship** | `appointment_clause`, `guardian_functions`, `revocation` | `guardians` | `guardians_act_jointly` |
| **Costs Agreement** | `the_work`, `our_fees`, `disbursements`, `billing_units_and_increases`, `fees_estimate`, `gst`, `interest`, `completion_estimate`, `billing_arrangements`, `itemised_bills`, `your_rights`, `costs_recovery_and_disputes`, `progress_reports`, `substantial_change`, `trust_money`, `authority_to_receive_moneys`, `costs_in_proceedings`, `engaging_another_practitioner`, `document_retention`, `company_signatories`, `legal_aid`, `suspension`, `termination`, `governing_law`, `email_communication`, `severability` | `work_items` | `estimate_completion_gst`, `estimate_fees_expenses_gst` |

For example, when the Power of Attorney generator runs, it specifically asks for `appointment_clause`. Calling that block `attorney_appointment`, `appointment`, or `Appointment Clause` will not work.

Every generator also accepts one further, optional clause not listed above: `front_matter`. All four generators otherwise open the document with a fixed, built-in introductory paragraph (for example, the Will's "This is the last will and testament of…" sentence, or the Costs Agreement's BETWEEN/AND/DATED lines and offer/acceptance wording). Tagging `[[CLAUSE:front_matter]]` in the DOCX replaces that built-in wording entirely with the precedent author's own — leave it out to keep the built-in default. See "Example 7: optional front matter override" below.

#### How to type a marker correctly in Microsoft Word

Follow these literal steps for every marker line:

1. Open the approved DOCX in Microsoft Word and turn on **Home → ¶ Show/Hide**. This makes paragraph boundaries visible.
2. Click at the start of a new, empty paragraph.
3. Type the marker using normal keyboard characters. For example: `[[CLAUSE:revocation]]`.
4. Press **Enter once**. Do not press Shift+Enter; Shift+Enter creates a line break inside the same paragraph.
5. Type or paste the approved clause wording in the following paragraph(s).
6. At the end of the clause, press Enter to make another empty paragraph.
7. Type `[[/CLAUSE]]` and press Enter.
8. Leave marker paragraphs as plain body paragraphs. Do not make them list items, headers, footers, text boxes, table cells, comments, or tracked-deletion text.
9. Save using **File → Save As → Word Document (`.docx`)**. Do not save as `.doc`, PDF, RTF, or an online-document shortcut.

In Word with ¶ displayed, a correct three-paragraph clause conceptually looks like this:

```text
[[CLAUSE:revocation]]¶
I revoke all prior powers of attorney made by me.¶
[[/CLAUSE]]¶
```

The `¶` symbols are displayed by Word; do not type them. The visible legal wording may contain multiple paragraphs or supported list items between the two boundary markers.

#### Character-by-character marker rules

- Use two ordinary opening square brackets `[[` and two closing square brackets `]]`.
- Use a colon `:` after `CLAUSE`, `IF`, or `REPEAT`—not a semicolon or hyphen.
- Closing markers contain a forward slash `/`: `[[/CLAUSE]]`, `[[/IF]]`, `[[/REPEAT]]`.
- Clause names begin with a letter and use letters, digits, and underscores only. Use `general_powers`, not `general powers` or `general-powers`.
- Group keys and aliases use letters, digits, and underscores. Copy the group key from Party Groups rather than retyping from memory.
- `AS` in a repeat is uppercase in the documented form: `[[REPEAT:attorneys AS attorney]]`.
- Placeholder braces are doubled: `{{answers.principal_name}}`. A single `{...}` is ordinary text and will not be replaced.
- Technical names are case-sensitive in practice. Always copy them exactly.
- Smart quotation marks are not part of any marker. Do not wrap a marker in quotes.
- Do not add a full stop after a boundary marker: `[[/CLAUSE]].` is wrong.
- Leading/trailing spaces are trimmed, but relying on them makes review harder. Keep marker lines clean.

#### Correct and incorrect Word paragraphs

| Correct | Incorrect | Why incorrect |
|---|---|---|
| `[[CLAUSE:revocation]]` | `Revocation [[CLAUSE:revocation]]` | Boundary marker is not the entire paragraph |
| `[[/CLAUSE]]` | `[[/Clause]]` | Wrong capitalisation/text |
| `[[IF:is_enduring]]` | `[[IF is_enduring]]` | Missing colon |
| `[[/IF]]` | `[[ENDIF]]` | Unsupported closing syntax |
| `[[REPEAT:attorneys AS attorney]]` | `[[REPEAT:attorney AS attorneys]]` | Group and alias reversed/wrong group key |
| `{{attorney.address}}` | `{{attorneys.address}}` | Placeholder must use the alias declared after AS |
| `{{answers.principal_name}}` | `{{answers.Principal's Full Name}}` | Must use field name, not screen label |
| Marker on its own normal paragraph | Marker embedded in a bullet paragraph | Control marker must occupy a clean paragraph |

#### What the words “group” and “alias” mean

In `[[REPEAT:attorneys AS attorney]]`:

- `attorneys` is the Party Group key. It represents the whole list of attorney rows saved in the request.
- `attorney` is a temporary singular name for the one row currently being produced.
- `{{attorney.name}}` reads the `name` field from that current row.
- After `[[/REPEAT]]`, the `attorney` alias no longer exists.

The alias does not need to match the label shown to staff, but a clear singular alias prevents mistakes: `beneficiaries AS beneficiary`, `attorneys AS attorney`, and `guardians AS guardian`.

#### What can be placed inside what

Use this nesting order:

```text
CLAUSE
 ├─ ordinary paragraphs/list items/placeholders
 ├─ IF ... optional ELSE ... /IF
 └─ REPEAT
     ├─ row placeholders using its alias
     └─ IF for a row-specific flag
        /IF
    /REPEAT
/CLAUSE
```

`IF` and `REPEAT` controls must be inside a named CLAUSE. An `IF` may be placed inside a `REPEAT`, as in the beneficiary per-stirpes example. Close inner controls before outer controls. Never cross them.

Correct closing order:

```text
[[CLAUSE:beneficiaries_clause]]
[[REPEAT:beneficiaries AS beneficiary]]
[[IF:beneficiary.per_stirpes]]
Conditional wording here.
[[/IF]]
[[/REPEAT]]
[[/CLAUSE]]
```

Incorrect closing order:

```text
[[CLAUSE:beneficiaries_clause]]
[[REPEAT:beneficiaries AS beneficiary]]
[[IF:beneficiary.per_stirpes]]
Conditional wording here.
[[/REPEAT]]
[[/IF]]
[[/CLAUSE]]
```

#### Safe formatting rules inside the clause

- Apply bold, italic, underline, and ordinary paragraph formatting to the legal wording, not to the marker lines.
- Use genuine Word numbered/bulleted lists when numbering must be preserved; do not simulate lists with spaces and manually typed numbers unless that is the approved design.
- Multi-level lists are supported within clauses, including repeated/conditional list items.
- Keep control markers out of tables. A table inside a marked clause is rejected by the current parser.
- Avoid text boxes, shapes, floating objects, headers, and footers for variable clause content; extracted preview may omit them.
- Accept/reject tracked changes and remove unresolved comments before upload. This reduces the risk of unintended or deleted wording entering the controlled template.
- Do not split a placeholder across differently formatted Word runs. Type/paste the complete token in one action and apply formatting to the entire token or containing sentence.
- Preserve an untouched approved master file outside LawDocs under the firm's document-control procedure.

#### The four pieces of template syntax

| Syntax | Purpose | Example |
|---|---|---|
| `[[CLAUSE:name]]` … `[[/CLAUSE]]` | Names a clause that the selected generator can retrieve | `[[CLAUSE:revocation]]` |
| `{{answers.field}}` | Inserts a questionnaire answer | `{{answers.principal_name}}` |
| `[[IF:flag]]` … `[[ELSE]]` … `[[/IF]]` | Includes one branch according to a supported condition | `[[IF:is_enduring]]` |
| `[[REPEAT:group AS alias]]` … `[[/REPEAT]]` | Repeats content once for each party row | `[[REPEAT:attorneys AS attorney]]` |

Every opening/closing control marker must occupy its own Word paragraph—the complete paragraph text, with no bullet, prefix, suffix, or comment. Write `[[CLAUSE:revocation]]`, not `Clause: [[CLAUSE:revocation]]`. Clause names cannot be duplicated or nested. Tables inside marked clauses are not supported. Normal text, bold styling, paragraphs, and multi-level Word lists inside supported clauses are preserved.

#### Example 1: simple named clause

Enter the following as three separate Word paragraphs:

```text
[[CLAUSE:revocation]]
I revoke all prior powers of attorney made by me.
[[/CLAUSE]]
```

The marker lines do not appear in the generated document. The generator requests the clause named `revocation` and inserts the middle paragraph with its Word formatting. The clause name must be one expected by that generator; merely inventing a new name does not make the generator use it.

#### Example 2: questionnaire placeholder

If Questionnaire Fields contains `principal_name` and `principal_address`, a clause can contain:

```text
This Power of Attorney is made by {{answers.principal_name}} of {{answers.principal_address}}.
```

For a request containing **Amara Osei** and **5 Bridge Road, Manly NSW 2095**, the generated text becomes:

```text
This Power of Attorney is made by Amara Osei of 5 Bridge Road, Manly NSW 2095.
```

The spelling after `answers.` must exactly match the field name—not its staff-facing label. `{{answers.Principal Name}}` and `{{principal_name}}` are invalid. An unresolved placeholder fails generation rather than silently leaving a blank token.

#### Example 3: conditional wording with ELSE

For the Power of Attorney generator, the supported `attorneys_act_jointly` flag can select one of two paragraphs:

```text
[[IF:attorneys_act_jointly]]
My Attorneys must act jointly in the exercise of this power.
[[ELSE]]
My Attorneys may act jointly and severally in the exercise of this power.
[[/IF]]
```

If the request answer is Yes, only the first paragraph is generated. If No, only the ELSE paragraph is generated. Without an `[[ELSE]]`, a false condition produces no content. Use only flags listed in **Available flags & party groups for this generator**; an unknown condition fails validation/generation.

#### Example 4: optional enduring clause

```text
[[CLAUSE:enduring_notice]]
[[IF:is_enduring]]
This Power of Attorney continues to have effect even if I lose capacity.
[[/IF]]
[[/CLAUSE]]
```

When **Enduring Power of Attorney?** is Yes, the sentence is included. When No, the named clause expands to no visible content. The legal wording remains the precedent author's responsibility.

#### Example 5: repeat attorney rows

The Party Groups tab defines group key `attorneys` with fields `name`, `address`, and `relationship`. The template may contain:

```text
[[CLAUSE:appointment_clause]]
[[REPEAT:attorneys AS attorney]]
I appoint {{attorney.name}} of {{attorney.address}} to be my Attorney.
[[/REPEAT]]
[[/CLAUSE]]
```

For two party rows—Kwame Osei and Linda Park—the appointment sentence is produced twice in the same order as the request rows. `attorneys` must exactly match the Party Group key and a group supported by the generator. `attorney` is the local alias used only inside this repeat. A placeholder such as `{{attorney.name}}` outside its repeat is out of scope and fails generation.

#### Example 6: nested repeat and conditional beneficiary wording

For a `beneficiaries` group with fields `name`, `share`, and the enabled per-stirpes option:

```text
[[CLAUSE:beneficiaries_clause]]
[[REPEAT:beneficiaries AS beneficiary]]
I give {{beneficiary.share}}% of my estate to {{beneficiary.name}}.
[[IF:beneficiary.per_stirpes]]
If {{beneficiary.name}} does not survive me, that share passes to their surviving children in equal shares.
[[/IF]]
[[/REPEAT]]
[[/CLAUSE]]
```

With David at 60%/per-stirpes Yes and Emily at 40%/per-stirpes No, both gift sentences are produced, but the survival paragraph appears only for David. The application supports this nesting. The exact legal language above is an explanatory example, not approved precedent wording.

#### Example 7: optional front matter override

Every generator opens the document with a fixed, built-in introductory paragraph unless the precedent tags its own `front_matter` clause. For the Will generator:

```text
[[CLAUSE:front_matter]]
This is the last will and testament of {{answers.testator_name}} of
{{answers.testator_street}}, {{answers.testator_suburb}} in the
{{answers.testator_state}}.
[[/CLAUSE]]
```

If `front_matter` is present, the generator uses only this wording in place of its built-in sentence — the rest of the document (Structure sections, clauses) is unaffected. If `front_matter` is absent, nothing changes from the previous behaviour. This is the one place besides an ordinary named clause where a firm can put its own approved opening wording under full document-control, without a developer.

For the Costs Agreement generator specifically, `front_matter` also has access to `{{answers.agreement_date}}` — today's date, formatted as it would appear in the built-in BETWEEN/AND/DATED lines. This value is generated automatically each time the document is produced; it is not a Questionnaire Field and does not need to be added to Questionnaire Fields.

#### Configuration-to-template matching checklist

Before upload, cross-check every technical identifier:

| In configuration | Must match in DOCX |
|---|---|
| Questionnaire field `principal_name` | `{{answers.principal_name}}` |
| Party Group key `attorneys` | `[[REPEAT:attorneys AS attorney]]` |
| Party field `address` | `{{attorney.address}}` inside that repeat |
| Per-stirpes support enabled | `[[IF:beneficiary.per_stirpes]]` where supported |
| Generator's required clause `appointment_clause` | `[[CLAUSE:appointment_clause]]` exactly once |

#### Upload and validation procedure

1. On **Details**, select the correct Document Generator first.
2. Return to **Template File** and read **Available flags & party groups for this generator**. Treat it as the allowed vocabulary for IF and REPEAT markers.
3. Upload the DOCX.
4. Read **Clause Marker Check**. Correct every unclosed, duplicate, nested, stray, unknown flag/group/field, or unsupported-structure message; then upload the corrected version.
5. Inspect **Extracted Text (preview)**. It is a flattened reference view (`#` headings and `-` list items), not an exact visual preview. Empty/missing text can indicate unsupported tables or text boxes.
6. Save the precedent as inactive while testing.
7. Create representative requests covering every branch: Yes and No conditions, one and multiple party rows, optional fields blank and filled, per-stirpes on/off, and boundary share values.
8. Review both generated DOCX and PDF for wording, ordering, numbering, formatting, missing content, and unresolved tokens.
9. Obtain the required solicitor/firm approval, then activate the precedent.

#### Beginner walkthrough: build a Power of Attorney DOCX from a blank file

This walkthrough is for learning the mechanics. Replace every example sentence with approved wording before production.

**Part A — configure LawDocs first**

1. Create/edit the precedent and select **Power of Attorney** as Document Generator.
2. On Questionnaire Fields, confirm these exact field names: `principal_name`, `principal_address`, `is_enduring`, and `attorneys_act_jointly`. The included setup also uses optional `principal_dob`.
3. On Party Groups, create key `attorneys` with row fields `name`, `address`, and `relationship`; set minimum rows to 1.
4. Save the precedent with **Active off**.

**Part B — prepare the DOCX**

1. Open a blank Word document and enable ¶ Show/Hide.
2. Type each line below as its own paragraph. After every marker line, press Enter—not Shift+Enter.

```text
[[CLAUSE:appointment_clause]]
[[REPEAT:attorneys AS attorney]]
I appoint {{attorney.name}} of {{attorney.address}} to be my Attorney.
[[/REPEAT]]
[[IF:attorneys_act_jointly]]
My Attorneys must act jointly in the exercise of this power.
[[ELSE]]
My Attorneys may act jointly and severally in the exercise of this power.
[[/IF]]
[[/CLAUSE]]

[[CLAUSE:enduring_notice]]
[[IF:is_enduring]]
This Power of Attorney is intended to continue if I lose capacity.
[[/IF]]
[[/CLAUSE]]

[[CLAUSE:general_powers]]
Approved general powers wording goes here.
[[/CLAUSE]]

[[CLAUSE:revocation]]
Approved revocation wording goes here.
[[/CLAUSE]]
```

3. Use blank ordinary paragraphs between clauses only for editing readability; do not place an extra marker outside a clause.
4. Apply the approved formatting to the legal-content paragraphs.
5. Save as `Power-of-Attorney-NSW-v1.docx`. A controlled filename is helpful, although LawDocs uses the stored precedent title/record rather than the filename to select it.

**Part C — upload and perform the first check**

1. In LawDocs, open the Template File tab and upload the DOCX.
2. Wait for the upload/save operation to complete.
3. If **Clause Marker Check** contains any text, treat it as a failed check. Do not activate the precedent.
4. In **Extracted Text**, confirm all four clause names' content is represented and the legal paragraphs are in the expected order.
5. If content is missing, simplify the Word structure and remove text boxes/tables before uploading a corrected file.

**Part D — test every branch**

Create at least these four requests with fictional data:

| Test | Attorneys | Enduring? | Act jointly? | Expected result |
|---|---:|---|---|---|
| 1 | One | Yes | Yes | One appointment; enduring paragraph included; joint wording included |
| 2 | One | No | No | One appointment; enduring paragraph absent; joint-and-several wording included |
| 3 | Two | Yes | No | Two appointments in entered order; enduring paragraph; joint-and-several wording |
| 4 | Two | No | Yes | Two appointments; no enduring paragraph; joint wording |

For every generated document, inspect the DOCX—not only the extracted preview. Search the output for `[[`, `]]`, `{{`, and `}}`; none should remain. Check headings, page breaks, numbering, punctuation, singular/plural wording, blank sections, and PDF conversion.

#### How to respond to common Clause Marker Check messages

| Message meaning | Typical mistake | Correction |
|---|---|---|
| Clause never closed | `[[CLAUSE:...]]` exists but no later `[[/CLAUSE]]` | Add the closing marker on its own paragraph |
| Closing marker has no matching opener | Extra `[[/CLAUSE]]`, `[[/IF]]`, or `[[/REPEAT]]` | Remove it or add the correct opener in the proper order |
| Duplicate clause | Same clause name used twice | Combine the content into one named clause; each name appears once |
| Nested clause markers | A new CLAUSE begins before the previous CLAUSE closes | Close the first clause; clauses cannot contain clauses |
| Unknown flag | IF name is misspelled or unsupported by the generator | Copy an allowed flag from the on-screen reference |
| Unknown party group | REPEAT key differs from Party Groups/generator | Copy the exact group key, such as `attorneys` |
| Namespace/alias not in scope | Placeholder uses `attorney` outside its repeat or uses a different alias | Move it inside the repeat or correct the alias |
| Unknown field | Placeholder field is not defined for answers/group rows | Correct the name or add/configure the intended field consistently |
| Tables not supported inside clauses | Legal content inside a Word table between clause markers | Redesign it as supported paragraphs/lists or obtain a developer-supported solution |

#### If upload passes but generation still fails

1. Open the failed Document Request and copy the exact **Error** text.
2. Do not keep resubmitting the same request.
3. Confirm the request contains all required answers and minimum party rows.
4. Confirm the precedent still uses the intended generator and uploaded file.
5. Compare the error's clause/placeholder name with Questionnaire Fields, Party Groups, and the DOCX.
6. Correct the inactive precedent, re-upload it, and repeat the complete test matrix.
7. Use **Regenerate** from the original request only after the configuration is corrected; verify all copied values before submission.

#### Final activation sign-off checklist

Do not switch **Active** on until each item is Yes:

- correct document category and jurisdiction;
- correct generator selected;
- exact required clause names present once each;
- all markers use their own paragraphs and close in the correct order;
- Clause Marker Check is empty;
- extracted preview contains the expected wording;
- all questionnaire placeholders match configured field names;
- all repeat groups/aliases/row fields match configuration;
- every Yes/No and row-count test has been generated;
- DOCX formatting and PDF conversion have been visually reviewed;
- no marker tokens or sample data remain in output;
- solicitor/authorised reviewer has approved the legal wording;
- controlled version/source and approval evidence have been retained.

Do not activate a precedent with a marker error, unreliable extraction, untested conditional branch, or unreviewed output.

### Structure tab

The Structure tab controls which sections a generated document contains, their order, their headings, and whether each one is shown — without a developer touching code. Leave it empty to use the selected generator's built-in default section list, unchanged.

#### Add or reorder a section step by step

1. Select **Add section**.
2. Enter **Section heading** — the visible text, for example `Beneficiaries`. Do not type a number; numbering is automatic and always renumbers sequentially, skipping any section that is hidden by its condition.
3. Choose **Content source**:
   - **Clause tag (from the .docx)** — the normal case. Enter the exact `[[CLAUSE:...]]` tag name in **Clause tag**.
   - **Computed block (from the generator)** — only for the small number of sections a generator computes in code rather than reading from a clause tag (for example, the Will's executor-appointment paragraph). Enter the exact key shown under **Computed blocks** in the Template File tab's on-screen reference.
4. Optionally enter **Show only if** using the same `[[IF:...]]`-style flag name grammar as inside the DOCX, to hide the section entirely when the condition is false. Leave blank to always show it.
5. Drag sections into the required order. Collapse each item once configured.
6. Save the precedent. **Clause Marker Check** re-validates every Structure entry against the uploaded DOCX and the selected generator — an unknown tag, unknown computed-block key, or unknown condition flag is reported the same way as a DOCX marker error.
7. Generate a test request and confirm the section order, headings, numbering, and visibility match expectations.

#### What Structure can and cannot do

- It can add, remove, reorder, rename (via heading text), and conditionally show/hide whole sections, for any of the four included generators.
- It cannot invent a new condition flag, party group, or computed-block key that the selected generator does not already advertise — those still require a developer. The Structure tab only re-arranges vocabulary the generator already exposes.
- It does not control a generator's fixed opening wording — use an optional `front_matter` clause tag for that (see "Example 7: optional front matter override" above).
- Leaving Structure empty is not a stripped-down mode — it simply uses the generator's own built-in default order, which is exactly what every precedent used before Structure existed.

### Formatting tab

The Formatting tab sets the font and heading style for this precedent's generated documents, overriding the firm-wide default set in System Settings → Document Defaults (see Section 13). Leave any field blank to keep using that firm-wide default.

| Field | Effect |
|---|---|
| **Font Family** | Base font name for the generated document, for example `Times New Roman`. |
| **Font Size (pt)** | Base body text size, 8–24pt. |
| **Heading Weight** | Bold or Not bold for every generated heading in this document. |
| **Heading Size Step (pt per level)** | How many points larger each heading level is than the one below it — 0 makes every heading level the same size. |

Formatting only affects generator-authored text: headings, `front_matter`, and computed blocks. Text captured verbatim from inside a `[[CLAUSE:...]]` block always keeps whatever formatting it has in the uploaded DOCX (bold, italic, paragraph spacing, list styles), unaffected by this tab — that has not changed. Use Formatting when one document type needs to look different from the rest of the firm's output (for example, a costs agreement printed in a smaller size to fit on fewer pages); use the DOCX's own Word styles for anything inside a clause.

### Questionnaire Fields tab

Questionnaire Fields create the single-value inputs that staff see on Step 2, **Details**, of a Document Request. Use them for facts that normally occur once per document: the principal's name, document date, address, a Yes/No instruction, or a fixed selection.

Do not use a Questionnaire Field for a list of people. Use a Party Group for attorneys, beneficiaries, guardians, or any other role that may have several rows.

#### Add a questionnaire field step by step

1. Select **Add field**.
2. Enter **Field name**. This is the permanent technical name used by the template and generator, for example `principal_name`.
3. Enter **Staff-facing label**, for example **Principal's Full Name**. This is what document-request users read.
4. Select **Input type**.
5. Set **Required** on when a request must not be submitted without a value.
6. For Select inputs, add each permitted stored value and displayed label under **Choices**.
7. Enter **Description (for reference)** explaining what the value means and how the generator uses it.
8. Collapse the item when complete, add further fields, and drag them into the order staff should complete them.
9. Save the precedent, create a test request, and confirm labels, order, validation, and choices.

#### Bulk import fields from CSV

Adding fields one at a time is fine for a handful of fields, but a new precedent with twenty or more fields is faster to set up from a spreadsheet. Select **Import Fields from CSV** at the top of the precedent edit screen.

1. Prepare a CSV with the header row `name,label,type,required,description,options`.
2. One row per field:
   - `name` — the technical field name (same rules as typing it by hand: lowercase, starts with a letter, letters/numbers/underscores only).
   - `label` — the staff-facing label.
   - `type` — one of `text`, `textarea`, `number`, `date`, `boolean`, `select`.
   - `required` — `yes`/`true`/`1` or `no`/`false`/`0`. Leave the column out of the CSV entirely to default every row to required, matching a new field's own default.
   - `description` — the reference note explaining the field.
   - `options` — only for `select` fields: pipe-separated `value=Label` pairs, for example `male=Male|female=Female`. Leave blank for any other type.
3. Upload the file and confirm.
4. LawDocs validates every row against the same rules as the on-screen form. A row that fails (bad name format, missing label/description, an unrecognised type) is skipped, not silently accepted — the confirmation notice lists exactly which rows were skipped and why. Fix the CSV and re-import to retry only the corrected rows.
5. A row whose `name` matches a field that already exists on this precedent updates that field in place rather than creating a duplicate; a genuinely new name is appended. If the same name appears twice within one CSV, the later row wins.
6. After import, the Questionnaire Fields list on screen updates immediately — check the order, labels, and choices, then save and test a request as usual.

CSV import is a faster way to fill in the same Questionnaire Fields tab described above — it does not create a different kind of field, and every field it creates is fully editable afterwards through the normal on-screen controls.

#### Choose the correct input type

| Type | Use it for | Example |
|---|---|---|
| **Text** | One short line | Full name, suburb, reference wording |
| **Textarea** | Several lines | Special instructions or a long address, only if the generator/template expects it |
| **Number** | Numeric value | Age or amount; use Party Group share fields for distributed percentages |
| **Date** | A calendar date | Date of birth |
| **Yes / No** | A true/false instruction | Enduring power? Attorneys act jointly? |
| **Select** | One value from a controlled list | Gender or another fixed legal option |

For a Select field such as `principal_gender`, Choices may be:

| Value saved by LawDocs | Label shown to staff |
|---|---|
| `male` | Male |
| `female` | Female |

The stored value can be used by generator logic, so do not change it casually after production use. Labels may be clearer for staff, but changes still require testing.

#### Worked questionnaire example

| Field name | Staff-facing label | Type | Required | Purpose |
|---|---|---|---|---|
| `principal_name` | Principal's Full Name | Text | Yes | Used in document title/opening text |
| `principal_dob` | Principal's Date of Birth | Date | No | Client record and age guidance |
| `principal_address` | Principal's Address | Text | Yes | Used in opening text |
| `is_enduring` | Enduring Power of Attorney? | Yes/No | Yes | Controls enduring wording |
| `attorneys_act_jointly` | Attorneys must act jointly? | Yes/No | Yes | Controls joint versus joint-and-several wording |

In a DOCX clause, `principal_name` would be referenced as `{{answers.principal_name}}`. The screen label **Principal's Full Name** is never placed inside the braces.

#### Field-name rules and change control

- Begin with a lowercase letter.
- Use only lowercase letters, numbers, and underscores.
- Use a clear subject prefix: `principal_address` is safer than the vague `address`.
- Never include spaces, punctuation, apostrophes, or hyphens.
- Do not create two fields with the same name.
- Treat a saved field name like a database key, not editable display wording.

Renaming or deleting a field can break client mappings, DOCX placeholders, generator logic, tests, and regeneration of old requests. Before changing one, search every configuration/template reference, keep the precedent inactive, and repeat all test cases.

#### What Questionnaire Fields cannot do

The current screen creates an ordered list of inputs; it does not provide an administrator button for arbitrary visual subheadings or collapsible form sections within the Details step. If the goal is a dynamic **document** section, use the supported clause/IF method described under “Create a dynamic document section.” If the goal is a new custom **screen** section or new generator-controlled behaviour, developer work is required.

### Client Mapping tab

Client Mapping connects a reusable Client record to the Questionnaire Fields of this specific precedent. It saves retyping and reduces spelling errors; it does not lock or verify the value.

The left side is fixed Client data: Full Name, DOB, Gender, Email, Phone, Street, Suburb, State, and Postcode. Each selector on the right lists the Questionnaire Fields already created for this precedent.

#### Configure client mapping step by step

1. Complete and save the Questionnaire Fields first.
2. Open **Client Mapping**.
3. For **Client's Full Name**, choose the questionnaire field that means the same thing—for example `principal_name`.
4. For **Date of Birth**, choose `principal_dob`.
5. Continue only where there is a true semantic match.
6. Leave **Not mapped** for client values the document does not request.
7. Save the precedent.
8. Create or open a fictional client with known values.
9. Start a new Document Request, select that client and this precedent, and confirm the intended fields prefill.
10. Change the client selector and the precedent selector during testing; confirm the correct values are reapplied and recheck all answers.

#### Example mapping

| Client attribute | Map to questionnaire field | Result on request |
|---|---|---|
| Client's Full Name | `principal_name` | Principal's Full Name prefills |
| Date of Birth | `principal_dob` | Principal's Date of Birth prefills |
| Street | `principal_street` if such a field exists | Street prefills separately |
| Email | Not mapped | Email remains unused by this request |

Mapping Street to `principal_address` would copy only the street value, not automatically build a full address from Street, Suburb, State, and Postcode. Either create separate questionnaire fields and use them as designed, maintain a suitable complete-address field through approved changes, or have staff enter the full address manually. Do not map several Client attributes to one target in the hope that LawDocs will concatenate them.

#### Mapping safety rules

- Map like to like: name to name, DOB to DOB, gender to gender.
- Never map a client phone number into an address or document instruction field merely because both are Text inputs.
- Mapping is precedent-specific. `testator_name` may be correct for a Will, while `principal_name` is correct for a Power of Attorney.
- Empty client attributes do not overwrite a field with useful data, but staff must still review the request.
- Prefilled fields remain editable. A user can correct a changed address for the current request; update the Client record too when appropriate.
- Client Mapping does not import related people. Contacts are imported through Party Groups on the request screen.

### Party Groups tab

Party Groups create repeatable sets of structured rows on the Document Request. Use a group whenever users may need to add one, two, or many people/items of the same role.

Examples are Beneficiaries, Attorneys, Guardians, or Executors. Each group can be repeated in the Word clause using `[[REPEAT:...]]`.

#### Add a party group step by step

1. Select **Add party group**.
2. Enter **Group key**, such as `attorneys`. This is the permanent technical plural name.
3. Enter **Staff-facing label**, such as **Attorneys**.
4. Select **Role type**: Beneficiary, Executor, Guardian, Attorney, or Custom.
5. Enter **Min rows**. Use 1 when at least one party is mandatory; use 0 only when the entire group is optional.
6. Enter **Max rows**, or leave blank for no application maximum.
7. If percentage distribution applies, enter **Share field name** matching one row field, such as `share`.
8. Enable **Supports substitute** only when rows can designate a specific fallback.
9. Enable **Supports per-stirpes** only when that concept is appropriate and supported by the generator/template.
10. Under **Fields captured per row**, add the data required for one party.
11. Save and test minimum/maximum rows, contact import, row ordering, validation, and generated repetition.

#### Example: Attorneys group

Group-level settings:

| Setting | Value |
|---|---|
| Group key | `attorneys` |
| Label | Attorneys |
| Role type | Attorney |
| Min rows | 1 |
| Max rows | blank |
| Share field | blank |
| Supports substitute | Off |
| Supports per-stirpes | Off |

Row fields:

| Field name | Row-facing label | Type | Required |
|---|---|---|---|
| `name` | Attorney's Full Name | Text | Yes |
| `address` | Attorney's Address | Text | Yes |
| `relationship` | Relationship to Principal | Text | No |

The DOCX connection is:

```text
[[REPEAT:attorneys AS attorney]]
I appoint {{attorney.name}} of {{attorney.address}} to be my Attorney.
[[/REPEAT]]
```

If staff enter three attorney rows, the sentence is generated three times in the displayed row order.

#### Example: Beneficiaries group with shares

Configure group key `beneficiaries`, label Beneficiaries, minimum 1, and Share field `share`. Add row fields `name`, `share`, and `gender`. Enable per-stirpes only when the Will generator/template supports it.

| Beneficiary row | `name` | `share` | `per_stirpes` |
|---|---|---:|---|
| 1 | David Sullivan | 60 | Yes |
| 2 | Emily Sullivan | 40 | No |

Because Share field is `share`, the application requires all rows to total exactly 100. The per-stirpes toggle is automatically added to each row when support is enabled; do not also create a manual field named `per_stirpes`.

#### Bulk import row fields from CSV

Row fields within a party group can be bulk-imported the same way Questionnaire Fields can (see "Bulk import fields from CSV" above), using **Import Party Group Fields from CSV** at the top of the precedent edit screen. The one difference is an extra required column, `group_key`, identifying which existing party group each row belongs to.

1. Create the party group itself first, on this tab, with its key, label, and role settings — the CSV only fills in a group's **row fields**, it does not create the group.
2. Prepare a CSV with the header row `group_key,name,label,type,required,description,options`.
3. `group_key` must exactly match an existing group's key on this precedent, for example `attorneys`. A row whose `group_key` does not match any existing group is skipped, with the reason given in the confirmation notice — it is not used to create a new group.
4. The remaining columns (`name`, `label`, `type`, `required`, `description`, `options`) follow the same rules as the Questionnaire Fields CSV import.
5. One CSV can define row fields for several different groups at once — each row's `group_key` routes it to the correct group. A row whose `name` matches a field already present in that same group updates it in place; a new name is appended to that group's field list. Fields in a group not mentioned in the CSV are left untouched.

#### Party field and contact-import design

Contacts are imported by matching standard field names. Fields such as `name`, `relationship`, `gender`, `email`, `phone`, `address`, `street`, `suburb`, `state`, `postcode`, and `dob` can receive matching saved contact data. A custom name such as `attorney_full_legal_name` will not automatically match the contact's standard `name` value.

Imported rows remain editable. Fields with no matching Client Contact value—such as `share`—remain blank and must be completed.

#### Party Group safety rules

- Group keys are plural and stable; aliases in Word are normally singular.
- Row field names use lowercase letters, numbers, and underscores only.
- The Share field setting must exactly match an existing numeric row field.
- Minimum/maximum values control the request UI; they do not decide substantive legal suitability.
- Row order is persisted and can affect generated wording.
- Avoid duplicate primary names when using Specific Substitute because name matching may be ambiguous.
- Do not enable substitute/per-stirpes controls unless the selected generator and approved clause wording consume them.
- Changing a group key or field name requires updating every REPEAT, alias placeholder, generator expectation, and test.

### Create a dynamic document section

“Dynamic section” can mean three different things in LawDocs:

| Desired result | Configuration to use |
|---|---|
| Add a new input dynamically to the request Details step | Add a Questionnaire Field |
| Add a repeatable block of people/items to the request | Add a Party Group |
| Include, exclude, or repeat wording in the generated document | Use a supported named CLAUSE with IF and/or REPEAT markers |

There is no generic **Add Dynamic Section** button. The chosen generator controls which named clauses and IF flags are available. If a new section needs a flag or clause the generator does not advertise, an administrator cannot invent it in the UI; a developer must extend the generator first.

#### Example A: dynamic Yes/No document section using an existing flag

Goal: show an Enduring Power Notice only when the user answers Yes to **Enduring Power of Attorney?**

1. **Questionnaire Fields:** create `is_enduring`, label **Enduring Power of Attorney?**, type Yes/No, Required on.
2. **Generator:** select Power of Attorney. Its on-screen reference lists `is_enduring` as an allowed IF condition. The required-clause table in this manual confirms that this generator uses `enduring_notice` (custom generators require developer documentation).
3. **Template File:** enter:

```text
[[CLAUSE:enduring_notice]]
[[IF:is_enduring]]
Approved enduring notice wording goes here.
[[/IF]]
[[/CLAUSE]]
```

4. Test request 1 with Yes: the section wording must appear.
5. Test request 2 with No: the wording must be absent; check that an unwanted blank paragraph/page is not created.

This is dynamic because a request answer controls whether the clause has visible content.

#### Example B: dynamic alternative wording using IF/ELSE

Goal: choose joint or joint-and-several wording.

1. Create Questionnaire Field `attorneys_act_jointly`, type Yes/No, Required on.
2. Confirm Power of Attorney lists it as an allowed flag.
3. Inside `appointment_clause`, add:

```text
[[IF:attorneys_act_jointly]]
Approved wording for attorneys who must act jointly.
[[ELSE]]
Approved wording for attorneys who may act jointly and severally.
[[/IF]]
```

4. Generate one Yes and one No test and compare both outputs with approved wording.

#### Example C: dynamic repeated section using a Party Group

Goal: generate one appointment paragraph for every attorney.

1. Create Party Group key `attorneys` with fields `name`, `address`, and `relationship`.
2. Confirm the Power of Attorney generator lists `attorneys` as an allowed group.
3. Put this inside `appointment_clause`:

```text
[[REPEAT:attorneys AS attorney]]
I appoint {{attorney.name}} of {{attorney.address}} to be my Attorney.
[[/REPEAT]]
```

4. Test zero rows (the request should be blocked when Min rows is 1), one row, and several rows.
5. Confirm the output count and order exactly match the request rows.

#### Example D: request input dynamically prefilled from a client

Goal: prefill Principal's Full Name without retyping.

1. Create Questionnaire Field `principal_name`.
2. In Client Mapping, map Client's Full Name to `principal_name`.
3. In the request wizard, select a saved Client and the configured precedent.
4. Confirm Principal's Full Name is prefilled but still editable.
5. The generator can use the answer directly, and a marked clause can reference it as `{{answers.principal_name}}` where supported.

#### Dynamic-section verification matrix

For every dynamic section, document and test:

| Test question | Evidence required |
|---|---|
| What field/group controls it? | Exact technical name recorded |
| Does the selected generator support it? | Name visible in generator reference or developer confirmation |
| What happens when true/filled? | Expected paragraph(s) visible |
| What happens when false/blank? | Expected alternative or no section; no stray heading/page |
| What happens with one/many rows? | Correct count, order, numbering, and grammar |
| Does client mapping prefill correctly? | Test client produces expected editable values |
| Are DOCX and PDF both correct? | Visual sign-off for all branches |

### Complete worked configuration: Power of Attorney

This example shows how all tabs connect.

1. **Details:** Title = Power of Attorney; Category = power of attorney; Jurisdiction = NSW; Generator = Power of Attorney; Requires review = On; Active = Off during setup.
2. **Questionnaire Fields:** add `principal_name` (Text, required), `principal_dob` (Date, optional), `principal_address` (Text, required), `is_enduring` (Yes/No, required), and `attorneys_act_jointly` (Yes/No, required).
3. **Client Mapping:** map Client Full Name to `principal_name` and Date of Birth to `principal_dob`. Other attributes may remain unmapped unless equivalent questionnaire fields exist.
4. **Party Groups:** key `attorneys`, label Attorneys, role Attorney, minimum 1; add fields `name` (required Text), `address` (required Text), and `relationship` (optional Text).
5. **Template File:** provide exactly named clauses expected by the generator—`appointment_clause`, `enduring_notice`, `general_powers`, and `revocation`. Inside `appointment_clause`, repeat `attorneys AS attorney` and use `{{attorney.name}}`/`{{attorney.address}}`. Use the supported `attorneys_act_jointly` IF/ELSE. Inside `enduring_notice`, use `[[IF:is_enduring]]`.
6. Save, inspect Clause Marker Check and extracted preview, then generate test cases for one attorney/Yes enduring, two attorneys/No enduring, jointly Yes, and jointly No.
7. Confirm output and approval before switching Active on.

The safest design sequence is **generator → questionnaire fields → party groups → client mapping → DOCX markers → test requests → approval → activation**. Changing a technical key later requires checking every downstream reference.

## 11. User administration (super administrator)

### Screen: Users list / New User / Edit User

1. Select **New User**.
2. Enter Name, unique valid Email, Password (minimum eight characters), and Confirm Password.
3. Assign one or more roles. Avoid granting `super_admin` unless full control is required.
4. Under **Precedent Access**, optionally restrict an operator to categories. A blank selection means unrestricted; it does not mean no access.
5. Select **Create**.

When editing, leave Password blank to retain it. The list can be searched, sorted, filtered by role, and displays verification status. Deleting an account is consequential; preserve required request/audit ownership under firm policy before deletion.

## 12. Audit Log (administrator)

### Purpose, access, and integrity

Open **Administration → Audit Log**. It is a chronological, read-only record used to investigate record changes, access-control changes, authentication activity, and document-file activity. It is visible only to users granted Audit Log view permissions—normally super administrators.

Audit entries cannot be created, edited, or deleted from this screen. This protects the value of the trail. The log supports operational investigation and accountability, but it is not automatically proof of legal compliance and does not replace the firm's formal monitoring, retention, or incident-response processes.

### Screen: Audit Log list

The newest entry appears first. Columns are:

| Column | Meaning |
|---|---|
| **When** | Server-recorded date and time to the second |
| **Area** | Activity category, such as `auth`, `access_control`, `document`, `client`, `precedent`, or `document_request` |
| **Activity** | Human-readable description, such as User logged in or a model was updated |
| **By** | User who caused the event; **System** when no authenticated actor is associated |
| **On** | Type of affected record, such as Client, Document, User, or DocumentRequest |
| **Event** | Created, Updated, Deleted, or another activity type |

Use search for words in **Activity**. Sort by When/Area where offered, or use filters:

- **Area** — show one activity category.
- **Event** — Created, Updated, or Deleted.
- **From / Until** — inclusive calendar-date range.

### Find an event step by step

Example: determine who changed a precedent during a known week.

1. Expand **Administration** and open **Audit Log**.
2. Open Filters.
3. Set **Area** to `precedent`.
4. Set **Event** to Updated.
5. Set **From** and **Until** to the investigation dates.
6. Apply the filters.
7. Review When, Activity, By, and On.
8. Select **View** on the relevant row for exact details.
9. Record the audit entry URL/identifier and findings according to firm procedure; do not alter business records merely to make the log look cleaner.

Clear or reset filters before starting a different investigation. An empty result may mean the filters are too narrow, the event is outside retention, activity logging was unavailable/disabled, or the action is not among the instrumented events.

### Screen: View Audit Log Entry

The **Activity** section displays:

- **When** — exact recorded timestamp;
- **Area** — source category;
- **Activity** — event description;
- **Performed by** — actor or System;
- **On** — affected model and numeric record ID, for example `Client #24`;
- **IP address** — when the event captured a request IP.

The **Changed values** section appears when attribute changes were recorded:

- **Before** shows old stored values.
- **After** shows new stored values.

The **Other properties** section appears for additional context, such as assigned role/permission names, authentication guard, or email entered during a failed login.

### Interpret common audit areas

| Area/event | What it normally records | Example investigation use |
|---|---|---|
| `auth` | Successful login, logout, failed login; IP and guard where captured | Review suspicious sign-in attempts |
| `access_control` | Roles or permissions assigned/removed | Confirm who granted elevated access |
| `user` | Staff-account field changes | Review account/profile administration |
| `client` / related records | Client/contact creation and changes | Identify who corrected personal data |
| `precedent` | Template configuration changes | Trace a field, generator, or activation change |
| `document_request` / parties/witnesses | Request lifecycle and related-record changes | Reconstruct approval/signing-data activity |
| `document` | File Manager record creation, rename/move/path changes, copy creation, deletion | Trace working-file management |
| `setting` | System-setting changes | Review security, branding, mail, or generation settings |

Area names reflect the underlying activity configuration and can differ after custom development.

### Before-and-after example

An Updated precedent entry might show:

| Before | After | Interpretation |
|---|---|---|
| `is_active: false` | `is_active: true` | The precedent was activated |
| `requires_review: true` | `requires_review: false` | Approval gate was disabled; investigate authorisation |

Read the complete entry, actor, timestamp, and related operational context. A changed value says what was stored before/after; it does not explain the person's intention.

### Authentication and access review

For a suspected account incident:

1. Filter **Area** to `auth` and set the date range.
2. Inspect successful and failed login activity, actors/emails, and IP addresses.
3. Filter **Area** to `access_control` for role/permission changes around the same time.
4. Review `user` entries for account changes.
5. Follow the firm's incident-response process. Preserve evidence and escalate; do not delete the user or related records impulsively.

An IP address identifies a network endpoint, not necessarily an individual. Shared networks, proxies, VPNs, and mobile carriers require careful interpretation.

### Audit Log limitations and safe handling

- “System” means no user was associated with that entry; it does not automatically mean a scheduled task.
- The trail covers configured model and event activity, not every mouse click or document view.
- Audit data may contain personal, security, and changed-value information. Restrict access and exports/screenshots.
- Times use the application's displayed/server convention; account for timezone when comparing external evidence.
- Retention depends on system configuration and operational maintenance.
- If an expected entry is missing, do not conclude the action did not occur. Escalate with the affected record, approximate time, user, and expected action.
- Never share audit details through an unapproved channel.

## 13. System Settings (super administrator)

Select **System Settings**, change one or more tabs, then select **Save**. Appearance changes normally require a refresh.

### General

- **Application Name** — header/login brand name.
- **Tagline** — login-page supporting text.

### Appearance

- Color themes: Indigo, Amber Gold, Emerald, Rose, Violet, Sky Blue, Teal, Orange, Slate, Gray, Blue, Cyan, Purple, Pink.
- Panel modes: Light, Dark, System, High Contrast, Sepia, Midnight.
- Branding: Application Logo, App Icon/Favicon, and alternative Favicon. The alternative Favicon overrides the App Icon for browser tabs.

### Document Defaults

- Set generated DOCX font family and size (8–24 pt).
- **Generate documents in the background** must be enabled only when a continuously supervised queue worker is operating. Otherwise requests remain Pending indefinitely.

### Security

- **Allow two-factor authentication** is the firm-wide master switch. Individual users enroll through Profile.

### Email

- Configure From Name, From Address, and optional Staff Notification Email for generation-failure alerts.
- Enter SMTP Host, Port, Username, Password, and TLS/SSL only for an approved mail provider. A blank host keeps the default non-delivery/log-style configuration.

## 14. Troubleshooting

| Symptom | Likely cause | Action |
|---|---|---|
| A precedent is absent from the wizard | Inactive, invalid, deleted, category-restricted, or not permitted | Ask a precedent administrator to verify Active status, validation, category, and your role. |
| Client data did not prefill | No client selected or no client-field mapping on that precedent | Select both client and precedent; enter manually or ask an operator to configure mapping. |
| Contact import is absent | No selected client, no saved contacts, or no party group | Save contacts against the client and reopen/reselect as needed. |
| Beneficiaries will not submit | Share field does not total exactly 100 | Correct every row, including blank/decimal values, to total 100. |
| Request remains Pending | Background processing enabled without a working queue worker | Do not create duplicates; notify the administrator. |
| Status is Failed | Template marker, placeholder, file, or generation problem | Open View, copy the Error message, notify an operator, then Regenerate after correction. |
| Download buttons are missing | Not Completed, approval still required, insufficient permission; PDF converter may be unavailable | Check Status and Approval; request approval. Use DOCX if only PDF is unavailable. |
| Cannot add a witness | Witness name matches a recorded document party | Confirm independence and spelling; do not bypass the control without legal review. |
| Branding image does not appear | Public storage link/configuration issue | Administrator should verify storage publication; do not repeatedly upload duplicates. |
| 2FA code fails | Device time drift, wrong account, expired code, or used recovery code | Wait for a fresh code, check automatic time, or use one unused recovery code. |

When escalating, provide the case reference, request ID/URL, status, exact error text, approximate time, and the action attempted. Do not email client data or documents through unapproved channels.

## 15. Data and audit notes

LawDocs stores staff accounts and roles; client identity/contact/address/notes; reusable client contacts; precedents and private DOCX templates; questionnaire and party-group configuration; requests, answer snapshots and party rows; generated DOCX paths; approvals; signature timestamps; witnesses; optional signed-document files; per-user File Manager folders/files and metadata; and read-only audit activity with actors, subjects, changed values, and selected request properties such as IP addresses. Templates, generated documents, executed copies, and File Manager content use private application storage; avatars and branding use public storage.

The current repository database inspected for this manual contains one administrator account and no migrated client, precedent, or document-request tables. The repository also includes an optional, non-default demo seeder with three sample clients, three starter precedents, example users, and example request states. Demo credentials/data must never be deployed as production credentials or treated as real legal precedents.

Follow the firm's retention, access-control, privacy, backup, and breach-response policies. Use least-privilege roles, keep 2FA enabled, avoid unnecessary personal data in Notes, and never treat deleting a UI record as proof that all backups or historical files have been erased.

## 16. Daily operating checklist

### Requesting staff

1. Confirm client identity, jurisdiction, document type, and instructions.
2. Update the Client and Contacts records.
3. Create the request; review every prefilled value.
4. Complete required fields and party rows; verify shares/order/substitutions.
5. Add a case reference and submit once.
6. Monitor status and resolve failures without duplicating requests.
7. Review the generated draft and obtain approval where required.
8. Download through LawDocs and follow controlled signing procedures.
9. Record sent/signed timestamps, witnesses, and executed copy.

### Reviewing staff

1. Work from **Awaiting Review**.
2. Compare submitted answers, precedent/jurisdiction, party details, and output.
3. Correct through Regenerate; do not silently edit away an audit trail.
4. Approve only after the firm's complete review procedure.

### Administrators

1. Review failed and long-pending requests.
2. Maintain least-privilege users and category restrictions.
3. Validate and test precedent changes before activation.
4. Monitor queue, office/PDF conversion, email, private storage, backups, and access logs according to deployment policy.

---

**Document control:** This manual describes the application code and bundled data as inspected on 11 August 2026. Screen labels may change after future releases or local configuration. Firm procedure and current legal advice take precedence over this operational guide.
