# P3E-9C3B2 Disposable Operator Rehearsal

Status: complete. The merged P3E-9C3B1 command was rehearsed end to end against
a staged project and fresh current-schema disposable database.

The rehearsal generated exact P3E-9C1 authorization and P3E-9C2 claim evidence,
then proved:

- default dry run revalidated evidence and wrote no start or result;
- incomplete `--apply` confirmations were refused with zero additional rows;
- one exact fully confirmed apply invoked the final core-owned in-memory double
  once and committed the expected start/result rows and audits;
- replay was refused before a second invocation;
- the package registrar and handlers were never executed;
- credential, network, provider contact/mutation, real Checkout creation,
  payment, webhook, Store Lite mutation, retry, and client effects stayed zero;
  and
- database, grant, staged project, and configured-primary cleanup finished as
  `database:0 grant:0 staged-project:0 primary:unchanged`.

The rehearsal is implemented by
`scripts/sandbox-checkout-transport-operator-rehearsal.sh` with its isolated
fixture builder. It accepts no arguments, refuses database reuse, requires the
`redcms_acceptance_` disposable namespace, and owns cleanup through its exit
trap.

P3E-9C is complete. P3E-9D remains a separate explicit approval for one real
Stripe Sandbox Checkout Session, including a new restricted write key and one
exact provider POST. This rehearsal does not authorize that action.
