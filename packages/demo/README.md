# Kontor Demo

`KontorDemo` is an executable reference workflow, not a static fixture.
It connects real Kontor records through an order-to-cash scenario:

1. customer intake and CRM qualification;
2. catalog-backed quotation preparation;
3. explicit workflow approval;
4. order and project delivery;
5. invoice issuance and ledger posting;
6. payment allocation and settlement.

Every scenario stores the UIDs of the records it creates. Each transition
is transactional and may run only from its declared workflow state.
Outbound email uses a simulated transport: history and entity links are
created, but no message leaves the local installation.
