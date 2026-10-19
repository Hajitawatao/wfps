// Same rule as can_edit() in backend/lib/wfp.php: change both together.
// Encoders: own office, Draft or Returned, while encoding is open. Planning Office / admin: always.
export function canEdit(user, activity, encodingOpen) {
  if (!user) return false
  if (['admin', 'planning'].includes(user.role)) return true
  return user.role === 'encoder'
    && Number(activity.office_id) === Number(user.office_id)
    && ['draft', 'returned'].includes(activity.workflow)
    && !!encodingOpen
}
