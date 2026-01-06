<h2>Add Customer</h2>

<form method="post" action="<?= base_url('customers/store') ?>">
    <input name="name" placeholder="Name" required><br><br>
    <input name="email" placeholder="Email" required><br><br>
    <input name="phone" placeholder="Phone" required><br><br>

    <button type="submit">Save</button>
</form>
