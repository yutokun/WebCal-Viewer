<!DOCTYPE html>
<html lang="ja">

<head>
	<meta charset="UTF-8">
	<title>カレンダー</title>
	<script src="ical.min.js"></script>
	<link rel="icon" type="image/x-icon" href="favicon.png">
	<style>
		* {
			font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Krub, arial, "Hiragino Kaku Gothic Pro", "Noto Sans JP", Meiryo, sans-serif;
			-webkit-font-smoothing: antialiased;
		}

		/*
		* "Krub" is lisenced under the SIL Open Font License 1.1
		* by https://github.com/cadsondemak/Krub
		*/
		@font-face {
			font-family: 'Krub';
			font-style: normal;
			font-weight: 300;
			font-display: swap;
			src: url(/fonts/Krub-Medium.ttf);
		}

		@font-face {
			font-family: 'Krub';
			font-style: bold;
			font-weight: 700;
			font-display: swap;
			src: url(/fonts/Krub-Bold.ttf);
		}

		/*
		* "Noto Sans JP" is lisenced under the SIL Open Font License 1.1
		* by https://www.google.com/get/noto/
		*/
		@font-face {
			font-family: 'Noto Sans JP';
			font-style: normal;
			font-weight: 300;
			font-display: swap;
			src: url(/fonts/NotoSansJP-Regular.otf) format('opentype');
		}

		@font-face {
			font-family: 'Noto Sans JP';
			font-style: bold;
			font-weight: 700;
			font-display: swap;
			src: url(/fonts/NotoSansJP-Bold.otf) format('opentype');
		}

		h1:not(:first-child) {
			margin-top: 3em;
		}

		h1 {
			margin-bottom: 0;
		}

		h2 {
			font-size: 1em;
			margin: 0 auto;
		}

		p {
			font-size: 0.75em;
			margin: 0 0 0 0.75rem;
		}

		#loadingMessage, #welcome {
			height: calc(100vh - 1em);
			display: flex;
			justify-content: center;
			align-items: center;
			flex-direction: column;
			font-weight: bold;
			font-size: 2em;
		}

		#detail {
			font-size: 0.5em;
		}

		.event {
			margin-top: 2em;
			border-left: 3px solid black;
			padding-left: 0.5em;
		}

		h1+.event {
			margin-top: 1em;
		}

	</style>
</head>

<body>
	<div id="loadingMessage">
		<span>読み込み中</span>
		<span id="detail">カレンダーを DL しています</span>
	</div>
	<div id="welcome" style="display: none;">
		<span>ようこそ</span>
		<span id="detail">ここにカレンダーの URL を入力し、読み込みボタンを押して下さい。</span>
		<span id="detail" style="font-size:0.4em;">（カンマ区切り、スペースなしで記述すれば複数のカレンダーを使用できます。）</span>
		<form name="form">
			<input type="text" placeholder="iCalendar / WebCal" name="url" style="width: 20em;">
			<input type="button" value="読み込み">
		</form>
	</div>
	<?php
	$query = str_replace("webcal://", "https://", $_GET["webcal"]);
	$urls = explode(",", $query);
	foreach($urls as $url) {
		$text = file_get_contents($url);
		echo '<div class="data" style="display: none;">' . $text . '</div>';
	}
	?>
	<article>
	</article>
	<script>
		window.addEventListener("DOMContentLoaded", function() {
			let detail = document.getElementById("detail");
			detail.textContent = "イベントを分析しています";

			//イベントデータを取得
			setTimeout(() => {
				let eventData = constructCalendarData();
				setTimeout(drawPage(eventData));
			})

			function drawPage(eventData) {
				//今日のイベントを抽出
				let today = eventData.filter(function(value) {
					return value.startDate.toLocaleDateString().includes(getDateString(0));
				});
				drawCalendar("今日", today);

				//明日のイベントを抽出
				let tomorrow = eventData.filter(function(value) {
					return value.startDate.toLocaleDateString().includes(getDateString(1));
				});
				drawCalendar("明日", tomorrow);

				//明後日のイベントを抽出
				let dayAfterTomorrow = eventData.filter(function(value) {
					return value.startDate.toLocaleDateString().includes(getDateString(2));
				});
				drawCalendar("明後日", dayAfterTomorrow);

				document.getElementById("loadingMessage").style.display = "none";
			}
		});

		//フィルタ用の日付文字列を返します
		function getDateString(offsetFromToday) {
			let date = new Date();
			date.setDate(date.getDate() + offsetFromToday);
			return date.toLocaleDateString();
		}

		//古い順にソートされたイベントデータを返します
		function constructCalendarData() {
			let calendars = document.getElementsByClassName("data");

			console.log(calendars[0].textContent);
			if (calendars[0].textContent == "") {
				document.getElementById("loadingMessage").style.display = "none";
				document.getElementById("welcome").style.display = "";

				let button = document.querySelector('input[type="button"]');
				button.addEventListener('click', ()=>{
					location.href = "http://" + location.host + "/?webcal=" + document.form.url.value;
				});
			}

			let eventsData = [];
			for (let calendar of calendars) {
				let calData = ICAL.parse(calendar.textContent);
				let comp = new ICAL.Component(calData);
				let vevent = comp.getAllSubcomponents("vevent");
				let calName = calData[1][1][3];
				let calColor = calData[1][2][3];

				var i = 0;
				vevent.forEach(function(value) {
					let summary = value.getFirstPropertyValue("summary");
					let location = value.getFirstPropertyValue("location");
					let url = value.getFirstPropertyValue("url");
					let description = value.getFirstPropertyValue("description");

					//日時の構築
					let dtstart = value.getFirstPropertyValue("dtstart");
					let dtend = value.getFirstPropertyValue("dtend");
					let isAllDay = !dtstart.toString().includes("T");
					let startDate = new Date(dtstart);
					let endDate = new Date(dtend);
					let daysDiff = endDate.getDate() - startDate.getDate();

					let dt = createDateString();
					pushEvent();

					//繰り返しイベント
					let today = new Date();

					//繰り返し用の日付キャッシュ
					let tmp = new Date();
					tmp.setDate(tmp.getDate() - 1);
					let icalToday = ICAL.Time.fromJSDate(tmp);
					tmp.setDate(tmp.getDate() + 3);
					let ical3DaysAfter = ICAL.Time.fromJSDate(tmp);

					let rrule = value.getFirstPropertyValue("rrule");
					if (rrule) {
						var iter = rrule.iterator(dtstart);
						for (var next = iter.next(); next; next = iter.next()) {
							if (next.compare(icalToday) < 0) {
								//今日以前の場合
								continue;
							} else if (next.compare(ical3DaysAfter) > 0) {
								//3日後以降の場合
								break;
							}

							//ここは繰り返しイベントの日付が今日以降のもののエリア
							//ここでイベントを作成すればいいのか

							//終了日を過ぎていたら飛ばす
							if (rrule.until) {
								if (isAllDay && next.day >= rrule.until.day) break;
								else if (!isAllDay && next.day > rrule.until.day) break;
							}

							//todo 終日と1日（と日付をまたぐ場合？）で処理を分ける
							startDate = next.toJSDate();
							endDate.setFullYear(startDate.getFullYear());
							endDate.setMonth(startDate.getMonth());
							endDate.setDate(startDate.getDate() + daysDiff);

							//除外日なら飛ばす
							let isExdate = false;
							for (let exdate of value.getAllProperties("exdate")) {
								exdate = exdate.jCal[3].replace("T", " ");
								exdate = new Date(exdate);
								if (isAllDay) {
									//通常イベントから終日イベントに変更した際、変更前の時刻が残って正しく日時比較できない場合があるのに対処
									exdate.setHours(0);
								}
								if (new Date(exdate).valueOf() == startDate.valueOf()) {
									isExdate = true;
								}
							}
							if (isExdate) continue;

							dt = createDateString();
							pushEvent();
						}
					} else {
						today.setDate(today.getDate() - 1);
						if (startDate < today) return; //通常のループの continue と同義
					}

					//画面表示用の日時テキストを作成します
					function createDateString() {
						let dt;
						if (isAllDay) {
							//終日の場合
							//終わりの日は1日進んで見えるため、戻しておく
							endDate.setDate(endDate.getDate() - 1);
							let isOneDay = startDate.toDateString() == endDate.toDateString();

							//1日の場合と複数日の場合
							if (isOneDay) {
								dt = startDate.toLocaleDateString();
							} else {
								dt = startDate.toLocaleDateString() + " ～ " + endDate.toLocaleDateString();
							}
						} else {
							//終日ではない場合
							//日をまたがない場合とまたぐ場合
							if (startDate.toDateString() == endDate.toDateString()) {
								dt = startDate.toLocaleTimeString("ja-JP") + " ～ " + endDate.toLocaleTimeString("ja-JP");
							} else {
								dt = startDate.toLocaleString("ja-JP") + " ～ " + endDate.toLocaleString("ja-JP");
							}
						}
						return dt.replace(/\//g, "-").replace(/:00 |(:00)$/g, " ");
					}

					//イベントを配列に追加します
					function pushEvent() {
						eventsData.push({
							summary: summary,
							location: location,
							startDate: startDate,
							endDate: endDate,
							isAllDay: isAllDay,
							dt: dt,
							url: url,
							description: description,
							calName: calName,
							calColor: calColor
						});
					}
				});
			}

			eventsData.sort(function(a, b) {
				if (a.startDate < b.startDate) return -1;
				if (a.startDate > b.startDate) return 1;
				return 0;
			});

			return eventsData;
		}

		//カレンダーを HTML に描画します
		function drawCalendar(dayText, calendarEvents) {
			let article = document.getElementsByTagName("article")[0];
			article.innerHTML += "<h1>" + dayText + "の予定</h1>";
			if (calendarEvents.length >= 1) {
				calendarEvents.forEach(function(value) {
					//リンクは複雑なので事前に要素を作成
					let link = document.createElement("p");
					let anchor = link.appendChild(document.createElement("a"));
					anchor.textContent = value.url;
					anchor.href = value.url;
					anchor.target = "_blank";

					let eventFrame = article.appendChild(document.createElement("div"));
					eventFrame.className = "event"

					eventFrame.appendChild(document.createElement("h2")).textContent = value.summary;
					eventFrame.appendChild(document.createElement("p")).textContent = value.location;
					eventFrame.appendChild(document.createElement("p")).textContent = (value.isAllDay ? "終日：" : "") + value.dt;
					eventFrame.appendChild(link);
					eventFrame.appendChild(document.createElement("p")).innerHTML = value.description ? value.description.replace("\n", "<br>") : "";
					eventFrame.style.borderLeftColor = value.calColor;
				});
			} else {
				//イベントがなかった時
				article.appendChild(document.createElement("p")).textContent = "予定はありません";
			}

		}

	</script>
</body>

</html>
