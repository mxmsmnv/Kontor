# Kontor Demo

`KontorDemo` is an executable order-to-cash workflow. Installing the
module adds the Demo screen and prepares its storage. To create sample records
on a disposable installation, open **Admin → Kontor → Demo** and select
**New scenario**. Use the action shown at each step to continue through settlement.
The [demo installer](../../README.md#installation) installs the modules this
workflow needs in dependency order.

Each step adds the relevant linked records:

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
