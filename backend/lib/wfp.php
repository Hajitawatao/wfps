<?php
declare(strict_types=1);

// Domain rules of the WFP Encoding Sheet. Mirrored in frontend/src/lib/gate.js: change both together.

const FUNCTION_TYPES = ['Strategic', 'Core', 'Support'];                        // column A
const EXPENSE_CLASSES = ['MOOE', 'CO'];                                          // column H
const STATUSES = ['Obligated', 'Disbursed', 'Deferred', 'Ongoing', 'Conducted']; // column S
const WORKFLOWS = ['draft', 'submitted', 'returned', 'approved'];

const ACT_SELECT = "SELECT a.*, o.name office_name,
    ob.title objective, i.title indicator, c.name classification, l.name line_item, f.name fund_source
  FROM activities a
  JOIN offices o ON o.id = a.office_id
  JOIN objectives ob ON ob.id = a.objective_id
  JOIN indicators i ON i.id = a.indicator_id
  JOIN classifications c ON c.id = a.classification_id
  JOIN line_items l ON l.id = a.line_item_id
  JOIN fund_sources f ON f.id = a.fund_source_id";

/** Fiscal year asked for (?fy=), else the latest open year. */
function fy(): int
{
    $y = (int)input('fy', 0);
    return $y >= 2000 ? $y : (int)(scalar('SELECT MAX(fiscal_year) FROM settings') ?: date('Y'));
}

function encoding_open(int $fy): bool
{
    return (bool)scalar('SELECT encoding_open FROM settings WHERE fiscal_year = ?', [$fy]);
}

/** Encoders: own office, Draft or Returned, while encoding is open. Planning/admin: always. (= canEdit in gate.js) */
function can_edit(array $u, array $a): bool
{
    if (is_reviewer($u)) return true;
    return $u['role'] === 'encoder'
        && (int)$a['office_id'] === (int)$u['office_id']
        && in_array($a['workflow'], ['draft', 'returned'], true)
        && encoding_open((int)$a['fiscal_year']);
}

/** WHERE clause for lists and reports. Encoders only ever see their own office. */
function activity_filters(array $u): array
{
    $w = ['a.fiscal_year = ?'];
    $p = [fy()];
    if ($u['role'] === 'encoder') {
        $w[] = 'a.office_id = ?';
        $p[] = $u['office_id'];
    } elseif ($v = (int)input('office_id', 0)) {
        $w[] = 'a.office_id = ?';
        $p[] = $v;
    }
    if (in_array($v = (string)input('function_type', ''), FUNCTION_TYPES, true)) { $w[] = 'a.function_type = ?'; $p[] = $v; }
    $wf = (string)input('workflow', '');
    if ($wf === 'final') $w[] = "a.workflow IN ('submitted','approved')";
    elseif (in_array($wf, WORKFLOWS, true)) { $w[] = 'a.workflow = ?'; $p[] = $wf; }
    if (($s = trim((string)input('q', ''))) !== '') {
        $w[] = '(a.title LIKE ? OR a.responsible LIKE ?)';
        array_push($p, "%$s%", "%$s%");
    }
    return [implode(' AND ', $w), $p];
}

/** Loads one activity and checks the user may see it. */
function get_activity(array $u, int $id): array
{
    $a = one(ACT_SELECT . ' WHERE a.id = ?', [$id]);
    if (!$a) throw new HttpError(404, 'Activity not found.');
    if ($u['role'] === 'encoder' && (int)$a['office_id'] !== (int)$u['office_id']) throw new HttpError(403, 'This activity belongs to another office.');
    $a['can_edit'] = can_edit($u, $a);
    return $a;
}

/** Same rules as the Encoding Sheet: columns A-J required, at least one of K-N, O-R required. */
function validate_activity(array $u, array $in, ?array $existing): array
{
    $s = fn($k) => trim((string)($in[$k] ?? ''));
    $n = fn($k) => (int)($in[$k] ?? 0);
    $fy = $existing ? (int)$existing['fiscal_year'] : ($n('fiscal_year') ?: fy());
    if (!scalar('SELECT 1 FROM settings WHERE fiscal_year = ?', [$fy])) throw new HttpError(422, "FY $fy has not been opened yet.");

    $d = [
        'fiscal_year' => $fy,
        'office_id' => $u['role'] === 'encoder' ? (int)$u['office_id'] : ($n('office_id') ?: (int)($existing['office_id'] ?? 0)),
        'function_type' => $s('function_type'),
        'objective_id' => $n('objective_id'),
        'indicator_id' => $n('indicator_id'),
        'title' => $s('title'),
        'classification_id' => $n('classification_id'),
        'line_item_id' => $n('line_item_id'),
        'saa' => !empty($in['saa']) ? 1 : 0,
        'expense_class' => $s('expense_class'),
        'start_date' => $s('start_date'),
        'end_date' => $s('end_date'),
        'target_q1' => $s('target_q1') ?: null,
        'target_q2' => $s('target_q2') ?: null,
        'target_q3' => $s('target_q3') ?: null,
        'target_q4' => $s('target_q4') ?: null,
        'target_unit' => $s('target_unit'),
        'cost' => round((float)($in['cost'] ?? 0), 2),
        'fund_source_id' => $n('fund_source_id'),
        'responsible' => $s('responsible'),
        'impl_status' => $s('impl_status') ?: 'Ongoing',
    ];

    $e = [];
    if (!$d['office_id'] || !scalar('SELECT 1 FROM offices WHERE id = ?', [$d['office_id']])) $e['office_id'] = 'Choose the office.';
    if (!in_array($d['function_type'], FUNCTION_TYPES, true)) $e['function_type'] = 'Choose Strategic, Core or Support.';
    $obj = one('SELECT function_type FROM objectives WHERE id = ?', [$d['objective_id']]);
    if (!$obj || $obj['function_type'] !== $d['function_type']) $e['objective_id'] = 'Choose an objective under this type of function.';
    if ((int)scalar('SELECT objective_id FROM indicators WHERE id = ?', [$d['indicator_id']]) !== $d['objective_id']) $e['indicator_id'] = 'Choose a success indicator under this objective.';
    if ($d['title'] === '') $e['title'] = 'Write the activity.';
    if (!scalar('SELECT 1 FROM classifications WHERE id = ?', [$d['classification_id']])) $e['classification_id'] = 'Choose the activity classification.';
    if (!scalar('SELECT 1 FROM line_items WHERE id = ?', [$d['line_item_id']])) $e['line_item_id'] = 'Choose the budget line item.';
    if (!in_array($d['expense_class'], EXPENSE_CLASSES, true)) $e['expense_class'] = 'Choose MOOE or CO.';

    $sd = DateTime::createFromFormat('!Y-m-d', $d['start_date']);
    $ed = DateTime::createFromFormat('!Y-m-d', $d['end_date']);
    if (!$sd) $e['start_date'] = 'Enter the start date.';
    elseif ((int)$sd->format('Y') !== $fy) $e['start_date'] = "The start date must be within $fy.";
    if (!$ed) $e['end_date'] = 'Enter the end date.';
    elseif ((int)$ed->format('Y') !== $fy) $e['end_date'] = "The end date must be within $fy.";
    elseif ($sd && $ed < $sd) $e['end_date'] = 'The end date is before the start date.';

    if (!$d['target_q1'] && !$d['target_q2'] && !$d['target_q3'] && !$d['target_q4']) $e['targets'] = 'Enter a target in at least one quarter.';
    if ($d['target_unit'] === '') $e['target_unit'] = 'Enter the unit (trainings, activities, meetings, boxes…).';
    if (!is_numeric($in['cost'] ?? null) || $d['cost'] < 0) $e['cost'] = "Enter the cost in '000 PhP (0 if none).";
    if (!scalar('SELECT 1 FROM fund_sources WHERE id = ?', [$d['fund_source_id']])) $e['fund_source_id'] = 'Choose the source of funds.';
    if ($d['responsible'] === '') $e['responsible'] = 'Enter the responsible unit.';
    if (!in_array($d['impl_status'], STATUSES, true)) $e['impl_status'] = 'Choose the status.';

    if ($e) throw new HttpError(422, 'Some required columns are empty or wrong.', ['fields' => $e]);
    return $d;
}

/** Adds an area (office) unless one with that name exists. Code: given, else the initials of the name; made unique.
 *  Returns [id, code, created]. Used by offices-bulk.php and users.php. */
function ensure_office(string $name, ?string $code = null): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') throw new HttpError(422, 'Enter the name of the area.');
    if ($o = one('SELECT id, code FROM offices WHERE name = ?', [$name])) return [(int)$o['id'], $o['code'], false];
    if (!$code) {
        $initials = implode('', array_map(fn($w) => $w[0] ?? '', preg_split('/\s+/', $name)));
        $code = substr(preg_replace('/[^A-Za-z0-9]/', '', $initials), 0, 12) ?: 'AREA';
    }
    $code = $base = strtoupper($code);
    for ($k = 2; scalar('SELECT 1 FROM offices WHERE code = ?', [$code]) || scalar('SELECT 1 FROM users WHERE username = ?', [strtolower($code)]); $k++) $code = $base . $k;
    return [insert_row('offices', ['code' => $code, 'name' => $name]), $code, true];
}
