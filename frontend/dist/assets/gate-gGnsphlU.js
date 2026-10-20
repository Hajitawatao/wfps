function f(e,n,r){return e?["admin","planning"].includes(e.role)?!0:e.role==="encoder"&&Number(n.office_id)===Number(e.office_id)&&["draft","returned"].includes(n.workflow)&&!!r:!1}export{f as c};
