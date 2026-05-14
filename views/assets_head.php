<style>
/* ── Repetidor: formulário de registro ─────────────────────────── */
.rp-wrap{border:1px solid var(--bd2);border-radius:var(--r);background:var(--sf2);padding:10px}
.rp-header{display:flex;gap:8px;padding:0 4px 6px;border-bottom:1px solid var(--bd)}
.rp-col-lbl{flex:1;font-size:.7rem;font-weight:700;color:var(--mt);text-transform:uppercase;letter-spacing:.04em}
.rp-rows .rp-row{display:flex;gap:8px;align-items:flex-start;padding:8px;margin-top:6px;background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);transition:border-color .15s}
.rp-rows .rp-row:hover{border-color:var(--ac)}
.rp-cell{flex:1;min-width:0}
.rp-cell input,.rp-cell select,.rp-cell textarea{width:100%;margin:0;box-sizing:border-box}
.rp-cell textarea{resize:vertical;min-height:56px}
.rp-cell-rm{flex:0 0 34px;display:flex;align-items:center;justify-content:center;padding-top:2px}
.rp-add-btn{width:100%;margin-top:8px;border:1px dashed var(--bd2);background:transparent;color:var(--mt2);border-radius:var(--r2);padding:6px;font-size:.82rem;cursor:pointer;transition:all .15s;font-family:var(--font)}
.rp-add-btn:hover{border-color:var(--ac);color:var(--ac);background:rgba(0,212,255,.04)}

/* ── Repetidor: configuração de subcampos (tela de campos) ─────── */
.rp-cfg-row{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;margin-bottom:8px;padding:8px;background:var(--sf2);border:1px solid var(--bd);border-radius:var(--r2)}
.rp-cfg-row input[type=text],.rp-cfg-row input[type=number],.rp-cfg-row select{margin:0;box-sizing:border-box}
.rp-cfg-row input[name="rp_slug[]"]{width:130px;flex-shrink:0;font-family:monospace}
.rp-cfg-row input[name="rp_label[]"]{flex:1;min-width:80px}
.rp-cfg-row select[name="rp_type[]"]{width:130px;flex-shrink:0}
</style>
