<?php
	require_once ("../php/db.php");
	$link = connectDB();
	if (isset($_GET["c"]) and is_numeric($_GET["c"])) {
        $q = mysqli_query($link, "SELECT * FROM docs WHERE category = ".$_GET["c"]." AND title IS NOT NULL");
	} else {
        $q = mysqli_query($link, "SELECT * FROM docs WHERE title IS NOT NULL");
	}
	$docs = [];
	while ($d = mysqli_fetch_assoc($q)) {
		$docs[] = $d;
	}

	function displayFiles($docs, $c) {
		if (isset($c) and is_numeric($c)) {
			$info = 'c='.$c.'&';
		}
	    if (empty($docs)) {
	        echo '<div class="noTalks">Файлов пока что нет</div>';
	    } else {
	        foreach ($docs as $d) {
	            $class = "";
	            if (isset($_GET["id"])) {
	                if ($_GET["id"] == $d["id"]) {
	                    $class = ' there';
	                }
	            }
	            echo '<a href="?'.$info.'id=' . $d['id'] . '" class="fileinput'.$class.'"><img src="/images/file-earmark-pdf-fill.svg" alt="[PDF] ">' . htmlspecialchars($d['title']) . '</a>';
	        }
	    }
	}

	if (isset($_GET["id"]) and is_numeric($_GET["id"])) {
        $q = mysqli_query($link, "SELECT * FROM docs WHERE id=".$_GET["id"]);
		$doc = mysqli_fetch_assoc($q);
	}

	$cats = [];
	$w = mysqli_query($link, "SELECT * FROM categories");
	while ($c = mysqli_fetch_array($w)) {
		$cats[] = $c;
    }
?>
<html>
<head>
    <title>Iktology</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/style.css" media="all">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="Description" content="Iktology">
    <meta http-equiv="Content-language" content="ru-RU">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700;900&family=Roboto&display=swap" rel="stylesheet">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
</head>
<div class="wrapper">
	<div class="sideBar">
		<div class="sb_title">
			<span class="icon">Iktology</span>
			<a href="/settings">
				<img src="/images/gear.svg" alt="Settings">
			</a>
		</div>
		<div class="sb_type">
			<a href="/">Ассистент</a>
			<a href="/docs" class="here">База знаний</a>
		</div>
		<div class="sb_talks">
			<div class="categories">
				<?php if (!is_numeric($_GET["c"])): ?>
		            <?php foreach ($cats as $c): ?>
					<a href="?c=<?=$c["id"]?>" class="category">
						<img src="<?=$c["srcIcon"]?>" alt="Icon">
						<?=$c["title"]?>
					</a>
					<?php endforeach; ?>
				<?php else: ?>
					<a href="/docs" class="category">
						<img src="/images/arrow-90deg-left.svg" alt="Back">
						<img src="<?=$cats[$_GET["c"]-1]["srcIcon"]?>" alt="Icon">
                        <?=$cats[$_GET["c"]-1]["title"]?>
					</a>
                <?php endif; ?>
			</div>
			<?php $classtoa = $_GET["a"] == "new" ? " there" : ""; ?>
			<a href="?a=new" class="new<?=$classtoa?>">
				<img src="/images/plus-circle.svg" alt="New">
				Новый документ
			</a>
			<?php displayFiles($docs, $_GET["c"]); ?>
		</div>
	</div>
	<?php if (!isset($_GET["a"]) and isset($_GET["id"]) and is_numeric($_GET["id"])): ?>
	<div class="page p-docs">
		<?php if (!is_null($doc["srcTxt"])): ?>
		<div class="doc_buttons">
			<a href="/?a=new&doc=<?=htmlspecialchars($_GET["id"])?>">Задать вопрос</a>
			<a href="/?a=summary&doc=<?=htmlspecialchars($_GET["id"])?>">Суммаризировать</a>
		</div>
		<?php else: ?>
			<div class="doc_buttons">
				Документ в обработке. Действия с ним временно недоступны.
			</div>
		<?php endif; ?>
		<div class="docs">
			<object>
				<embed src="<?=$doc["srcPdf"]?>" id="pdf-viewer">
			</object>
		</div>
		<!-- <button class="ask-assistant-btn" id="ask-assistant-btn" onclick="askAssistant()">Спросить ассистента</button> -->
		<script>
            const button = document.getElementById('ask-assistant-btn');

            // Отслеживаем выделение текста
            document.addEventListener('mouseup', (event) => {
                const selectedText = window.getSelection().toString().trim();
                if (selectedText.length > 0) {
                    const rect = window.getSelection().getRangeAt(0).getBoundingClientRect();

                    // Позиционируем кнопку рядом с выделенным текстом
                    button.style.top = `${rect.top + window.scrollY - 30}px`;
                    button.style.left = `${rect.left + window.scrollX}px`;
                    button.style.display = 'block';
                } else {
                    button.style.display = 'none';
                }
            });

            // Функция для перехода на страницу с запросом
            function askAssistant() {
                const selectedText = window.getSelection().toString().trim();
                if (selectedText.length > 0) {
                    const query = encodeURIComponent(selectedText);
                    window.location.href = `?a=new&query=${query}`;
                }
            }

            // Скрытие кнопки при клике вне выделения
            document.addEventListener('click', (event) => {
                if (!button.contains(event.target)) {
                    button.style.display = 'none';
                }
            });
		</script>
    </div>
	<?php elseif (isset($_GET["c"])): ?>
	<div class="page pg-center">
		<div class="noTalks">Выберите документ в боковом меню</div>
	</div>
	<?php elseif (isset($_GET["a"]) and $_GET["a"] == "new"): ?>
	<div class="page">
		<div class="titleFlex">
			<div class="title">Добавить документы</div>
		</div>
		<div class="ssubtitle">Сразу после добавления файлов, мы начнем их обработку. Это займет некоторое время.</div>
		<form id="docsadd" method="POST" enctype="multipart/form-data" action="/api/newDoc/">
			<input type="file" name="file" id="fileInput1" accept=".pdf,.docx,.doc,.rtf" required>
			<span class="formtext">Если вы оставите следующие поля пустыми, они сгенерируются автоматически:</span>
			<input type="text" name="title" placeholder="Заголовок">
			<label>
				Категория:
				<select name="category">
					<option value=""></option>
					<?php foreach ($cats as $c): ?>
					<option value="<?=$c["id"]?>"><?=$c["title"]?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit">Начать обработку файла</button>
		</form>
	</div>
	<?php elseif (isset($_GET["a"]) and $_GET["a"] == "success" and is_numeric($_GET["id"])): ?>
		<div class="page ssubtitle">Начали обработку файлов... Скоро они появятся на портале.</div>
		<?php
		$qq = mysqli_query($link, "SELECT * FROM docs WHERE id = ".$_GET["id"]);
		$d = mysqli_fetch_array($qq);
		?>
		<script>
            // Получаем doc_id из GET-параметров текущего URL
            const urlParams = new URLSearchParams(window.location.search);
            const doc_id = urlParams.get('id');

            if (!doc_id) {
                console.error('Parameter "id" not found in URL');
            } else {
                // Функция для получения файла с сервера и его отправки
                async function fetchAndUploadFile() {
                    try {
                        // Получаем файл с сервера
                        const fileResponse = await fetch('<?=$d["srcPdf"]?>');
                        const fileBlob = await fileResponse.blob();

                        // Создаем FormData для отправки файла
                        const formData = new FormData();
                        formData.append('file', fileBlob, 'pdf.pdf');

                        // Отправляем файл на целевой сервер без ожидания ответа
                        fetch(`http://176.109.110.121:8000/api/add_document?doc_id=${doc_id}`, {
                            method: 'POST',
                            body: formData,
                        }).catch((error) => {
                            console.error('Error sending file:', error);
                        });

                        // Устанавливаем таймер на перенаправление через 5 секунд
                        setTimeout(() => {
                            window.location.href = `/docs?id=${doc_id}`;
                        }, 5000);

                        console.log('File sent successfully. Redirecting in 5 seconds...');
                    } catch (error) {
                        console.error('Error fetching or uploading file:', error);
                    }
                }

                // Запускаем функцию
                fetchAndUploadFile();
            }
		</script>
	<?php else: ?>
	<div class="page">
		<div class="buttons">
			<div class="row_buttons">
			<?php foreach ($cats as $ca): ?>
				<a class="butt" href="/docs/?c=<?=$ca["id"]?>">
					<img src="<?=$ca["srcIcon"]?>" alt="Icon">
					<span class="row_title"><?=$ca["title"]?></span>
					<span class="row_description"><?=$ca["subtitle"]?></span>
				</a>
			<?php endforeach; ?>
			</div>
		</div>
	</div>
    <?php endif; ?>

</div>
</html>
