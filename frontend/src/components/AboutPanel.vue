<template>
  <div class="help-body">
    <nav class="help-toc">
      <button v-for="s in sections" :key="s.id" :class="{ on: open === s.id }" @click="open = s.id">{{ s.title }}</button>
    </nav>

    <article v-if="open === 'start'">
      <h3>R1MC Work and Financial Plan Encoding System</h3>
      <p class="meta">Region I Medical Center, Arellano-Bani, Dagupan City, Pangasinan.</p>
      <p>It replaces the Excel WFP file. Instead of each office typing into one Encoding Sheet, every office signs in here and encodes its own rows.
        The Planning Office checks them, and the system prints WFP Form 1 exactly like the Print (GAA) and Print (Income) sheets.</p>
      <h3>The pages</h3>
      <dl>
        <dt>Summary</dt><dd>The dashboard. Total and Remaining MOOE/CO of the whole hospital (rows 1–2 of the Encoding Sheet), what each area requested, and the function summary. Filter by area (or Overall) and by which entries to count: all encoded, submitted + approved, or approved only.</dd>
        <dt>Encode an activity</dt><dd>The form for one row of the Encoding Sheet, columns A to S.</dd>
        <dt>Encoding Sheet</dt><dd>All rows you can see, laid out like the Excel sheet. Click a row to open it.</dd>
        <dt>Print (GAA) / Print (Income)</dt><dd>WFP Form 1, ready to print or save as PDF.</dd>
      </dl>
      <p>The fiscal year at the top right decides which year you are looking at and encoding for. The sun/moon button beside it switches between light and dark colors.</p>
      <p><b>Signatories</b>: WFP Form 1 is one document for the whole hospital. Prepared by (Planning Officer), Recommending approval (Medical Center Chief) and Approved by (Undersecretary of Health) are set by the Planning Office in General Info. When an area submits its activities, that counts as the area head's approval; the system records who submitted and when. A print of one area only is marked "Area copy" and is not the copy for signing.</p>
    </article>

    <article v-if="open === 'encode'">
      <h3>Encoding an activity</h3>
      <ol>
        <li>Click <b>Encode an activity</b>.</li>
        <li>Fill in the columns in order, the same as in Excel:
          <b>A</b> Type of function, then <b>B</b> Objectives, then <b>C</b> Success indicator. B only shows the objectives for the type you picked, and C only the indicators for that objective.</li>
        <li><b>D</b> Activity: write it the way it should appear on WFP Form 1, for example "Activity 9. HIS Improvements".</li>
        <li><b>E</b> Classification, <b>F</b> Budget line item, <b>G</b> SAA, <b>H</b> MOOE or CO.</li>
        <li><b>I–J</b> Start and end date, within the fiscal year.</li>
        <li><b>K–N</b> Target per quarter. Fill in at least one. <b>O</b> Unit: trainings, activities, meetings, boxes…</li>
        <li><b>P</b> Cost in thousand pesos. 1,500 means ₱1,500,000. Use 0 if the activity needs no budget.</li>
        <li><b>Q</b> Source of funds, <b>R</b> Responsible unit, <b>S</b> Status.</li>
        <li>Click <b>Save draft</b> to keep working on it later, or <b>Save and submit</b> when it is final.</li>
      </ol>
      <p>Long dropdowns can be searched: open one and start typing a few words, then press Enter.</p>
      <p>The box at the top shows Remaining MOOE and CO including this activity. A red number means the hospital is over the budget.</p>
    </article>

    <article v-if="open === 'submit'">
      <h3>Submitting and corrections</h3>
      <dl>
        <dt><span class="status s-draft">Draft</span></dt><dd>Saved, only your office sees it as work in progress. You can still change or delete it.</dd>
        <dt><span class="status s-submitted">Submitted</span></dt><dd>Sent to the Planning Office. You can no longer change it.</dd>
        <dt><span class="status s-returned">Returned</span></dt><dd>Planning sent it back. Their note is shown on the row. Fix it and submit again.</dd>
        <dt><span class="status s-approved">Approved</span></dt><dd>Accepted by Planning. It will be in the copy of WFP Form 1 for signing.</dd>
      </dl>
      <p>To submit many drafts at once, open the <b>Encoding Sheet</b> and click <b>Submit drafts</b>.</p>
      <p>Encoding can be closed by the Planning Office after the deadline. After that, ask Planning for any change.</p>
    </article>

    <article v-if="open === 'planning'">
      <h3>For the Planning Office</h3>
      <ol>
        <li><b>General Info</b>: set the names that print on Form 1 and the MOOE/CO budget in thousand pesos, as in the General Info sheet. Set the deadline, and close encoding when it is over.</li>
        <li><b>For review</b>: open each submitted activity, then <b>Approve</b> it or <b>Return to office</b> with a note. You can also tick several rows and approve them together.</li>
        <li><b>Print (GAA)</b> and <b>Print (Income)</b>: choose "Approved only" for the copy for signing, then <b>Print or save as PDF</b>. Choose A4, landscape.</li>
        <li>To change an approved activity, open it and click <b>Reopen as draft</b> or <b>Return to office</b>.</li>
        <li><b>Dropdown lists</b>: the choices of columns B, C, E, F and Q. Turn a value off instead of deleting it if it was already used.</li>
        <li>For a new year, open <b>General Info</b> and click <b>Open another fiscal year</b>.</li>
      </ol>
    </article>

    <article v-if="open === 'accounts'">
      <h3>Accounts and passwords</h3>
      <p><b>Forgot your password?</b> Click the link on the sign-in page and enter your username. MIS will see the request and give you a temporary password.
        You will be asked to set your own password when you sign in with it.</p>
      <p><b>Change your password</b> or <b>sign out</b>: click your name at the top right.</p>
      <p><b>No account yet?</b> Click <b>Request access</b> on the sign-in page. Choose your area, the access you need and your own username and password.
        You can sign in once MIS grants it.</p>
      <h3>Access levels</h3>
      <dl>
        <dt>Encoder</dt><dd>Own area only: encode, edit and submit its activities while encoding is open. Sees the area's budget and the hospital total.</dd>
        <dt>View only</dt><dd>Sees the Summary, Encoding Sheet and printouts of all areas. Cannot change anything.</dd>
        <dt>Procurement</dt><dd>Gives each area its MOOE and CO budget out of the hospital total (Budget per area). Sees all areas; cannot encode, approve or add areas.</dd>
        <dt>Planning Office</dt><dd>All areas, review and approve, General Info, dropdown lists, and adding areas (Areas). Sees every area's budget but cannot change it. User accounts are made by MIS.</dd>
        <dt>Administrator</dt><dd>Everything.</dd>
      </dl>
      <template v-if="role === 'admin'">
        <h3>For the administrator</h3>
        <ol>
          <li><b>Asking for access</b> appears at the top of <b>Users and offices</b>. Click <b>Grant access</b>, check the level and area, then confirm; or <b>Decline</b>.</li>
          <li>Go to <b>Users and offices</b>, then <b>Add areas from a list</b>. Paste one area per line, like <code>ER, Emergency Room</code>.
            Each area gets an encoder account. Print the list of usernames and temporary passwords right away; it is shown only once.</li>
          <li>When you add a single user, you can type a new area in the Office / area box and choose <b>Add "…" as a new area</b>. The area is created when you save.</li>
          <li>Password requests appear at the top of the same page. Click <b>Give a new password</b> and hand it to the user.</li>
          <li>Set an office inactive instead of deleting it once it has activities.</li>
        </ol>
      </template>
    </article>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({ role: String })
const open = ref('start')
const sections = computed(() => [
  { id: 'start', title: 'About the system' },
  { id: 'encode', title: 'Encoding an activity' },
  { id: 'submit', title: 'Submitting' },
  ...(['planning', 'admin'].includes(props.role) ? [{ id: 'planning', title: 'Planning Office' }] : []),
  { id: 'accounts', title: 'Accounts and passwords' }
])
</script>

<style scoped>
.help-body { display: grid; grid-template-columns: 190px 1fr; gap: 22px; }
.help-toc { display: flex; flex-direction: column; gap: 2px; }
.help-toc button { text-align: left; background: none; border: 0; padding: 8px 10px; border-radius: var(--r-sm); font: inherit; cursor: pointer; color: var(--ink-2); }
.help-toc button:hover { background: var(--paper); }
.help-toc button.on { background: var(--brand-wash); color: var(--brand); font-weight: 600; }
article { max-width: 62ch; line-height: 1.6; }
article h3 { font-size: 15px; margin: 0 0 6px; }
article h3 + p, article p + h3, article ol + h3, article dl + p { margin-top: 0; }
article p { margin: 0 0 12px; }
article .meta { color: var(--ink-3); font-size: 13px; }
article ol { list-style: decimal; padding-left: 20px; margin: 0 0 12px; display: grid; gap: 6px; }
article dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0 0 12px; }
article dt { font-weight: 600; }
article dd { margin: 0; color: var(--ink-2); }
code { background: var(--paper); padding: 1px 5px; border-radius: 4px; }
@media (max-width: 640px) { .help-body { grid-template-columns: 1fr; } .help-toc { flex-direction: row; flex-wrap: wrap; } article dl { grid-template-columns: 1fr; } }
</style>
