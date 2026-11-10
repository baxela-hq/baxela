<h1>{{ config('app.name') }}</h1>

<p>Se ha emitido una nota de crédito <strong>{{ $credit_note_number }}</strong> para la factura <strong>{{ $invoice_number }}</strong> del pedido <strong>{{ $order_code }}</strong>.</p>

<p>El PDF de la nota de crédito está adjunto a este correo.</p>
