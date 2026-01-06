<h2>Customers</h2>

<a href="<?= base_url('customers/create') ?>">Add Customer</a>

<table border="1" cellpadding="5">
    <tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Action</th>
    </tr>

    <?php foreach ($customers as $c): ?>
    <tr>
        <td><?= $c->id ?></td>
        <td><?= $c->name ?></td>
        <td><?= $c->email ?></td>
        <td><?= $c->phone ?></td>
        <td>
            <a href="<?= base_url('customers/edit/'.$c->id) ?>">Edit</a> |
            <a href="<?= base_url('customers/delete/'.$c->id) ?>">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
