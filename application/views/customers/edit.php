<h2>Edit Customer</h2>

<form method="post" action="<?= base_url('customers/update/'.$customer->id) ?>">
    <input name="name" value="<?= $customer->name ?>"><br><br>
    <input name="email" value="<?= $customer->email ?>"><br><br>
    <input name="phone" value="<?= $customer->phone ?>"><br><br>

    <button type="submit">Update</button>
</form>
