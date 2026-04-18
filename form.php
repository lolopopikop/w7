<div class="container">
<form action="index.php" method="POST">

    <div class="form-group">
        <label for="fio">ФИО:</label>
        <input type="text" id="fio" name="fio"
               value="<?= fd('fio', $formData) ?>"
               class="<?= isset($errors['fio']) ? 'error' : '' ?>">
        <?php if (isset($errors['fio'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['fio']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="phone">Телефон:</label>
        <input type="tel" id="phone" name="phone"
               value="<?= fd('phone', $formData) ?>"
               class="<?= isset($errors['phone']) ? 'error' : '' ?>">
        <?php if (isset($errors['phone'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['phone']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email"
               value="<?= fd('email', $formData) ?>"
               class="<?= isset($errors['email']) ? 'error' : '' ?>">
        <?php if (isset($errors['email'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['email']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="birthdate">Дата рождения:</label>
        <input type="date" id="birthdate" name="birthdate"
               value="<?= fd('birthdate', $formData) ?>"
               class="<?= isset($errors['birthdate']) ? 'error' : '' ?>">
        <?php if (isset($errors['birthdate'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['birthdate']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Пол:</label>
        <div class="radio-group">
            <label class="radio-label">
                <input type="radio" name="gender" value="male"
                    <?= (isset($formData['gender']) && $formData['gender'] === 'male') ? 'checked' : '' ?>>
                Мужской
            </label>
            <label class="radio-label">
                <input type="radio" name="gender" value="female"
                    <?= (isset($formData['gender']) && $formData['gender'] === 'female') ? 'checked' : '' ?>>
                Женский
            </label>
        </div>
        <?php if (isset($errors['gender'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['gender']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Язык программирования:</label>
        <?php
        $selectedLangs = isset($formData['languages']) ? $formData['languages'] : [];
        $langList = [
            1=>'Pascal',2=>'C',3=>'C++',4=>'JavaScript',5=>'PHP',
            6=>'Python',7=>'Java',8=>'Haskell',9=>'Clojure',
            10=>'Prolog',11=>'Scala',12=>'Go'
        ];
        ?>
        <select name="languages[]" multiple size="5"
                class="<?= isset($errors['languages']) ? 'error' : '' ?>">
            <?php foreach ($langList as $id => $name): ?>
                <option value="<?= $id ?>"
                    <?= in_array((string)$id, array_map('strval', $selectedLangs)) ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['languages'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['languages']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="bio">Биография:</label>
        <textarea id="bio" name="bio"><?= fd('bio', $formData) ?></textarea>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="contract"
                <?= (!empty($formData['contract'])) ? 'checked' : '' ?>>
            С контрактом ознакомлен
        </label>
        <?php if (isset($errors['contract'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['contract']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <button type="submit">Сохранить</button>
    </div>

</form>
</div>
