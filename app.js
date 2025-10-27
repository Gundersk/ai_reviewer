const form = document.getElementById('reviewForm');
const submitBtn = document.getElementById('submitBtn');
const loader = document.getElementById('loader');
const resultCard = document.getElementById('resultCard');
const resultText = document.getElementById('resultText');
const copyBtn = document.getElementById('copyBtn');

function toggleLoading(isLoading) {
  submitBtn.disabled = isLoading;
  loader.hidden = !isLoading;
}

function readFormData() {
  const name = document.getElementById('name').value.trim();
  const description = document.getElementById('description').value.trim();
  const rating = document.getElementById('rating').value;
  const lowLimitRaw = document.getElementById('low_limit').value;
  const upLimitRaw = document.getElementById('up_limit').value;

  if (!name || !description || !rating) {
    throw new Error('Пожалуйста, заполните обязательные поля.');
  }

  const payload = {
    name,
    description,
    rating: Number(rating)
  };

  // опциональные поля
  if (lowLimitRaw) payload.low_limit = Number(lowLimitRaw);
  if (upLimitRaw)  payload.up_limit  = Number(upLimitRaw);

  return payload;
}

async function generateReview(payload) {
  const resp = await fetch('./api/generate_review.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  const text = await resp.text(); // сначала сырой текст, чтобы поймать не-JSON
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error(`Некорректный ответ сервера:\n${text}`);
  }

  if (!resp.ok) {
    const msg = data?.error || `Ошибка сервера (${resp.status})`;
    throw new Error(msg);
  }

  // ожидаем формат { review: "..." }
  if (!data?.review) {
    throw new Error('Пустой ответ от бэкенда.');
  }
  return data.review;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  resultCard.classList.add('hidden');
  resultText.textContent = '';

  let payload;
  try {
    payload = readFormData();
  } catch (err) {
    alert(err.message);
    return;
  }

  try {
    toggleLoading(true);
    const review = await generateReview(payload);
    resultText.textContent = review.trim();
    resultCard.classList.remove('hidden');
  } catch (err) {
    alert(err.message);
  } finally {
    toggleLoading(false);
  }
});

copyBtn.addEventListener('click', async () => {
  const text = resultText.textContent.trim();
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    copyBtn.textContent = 'Скопировано!';
    setTimeout(() => (copyBtn.textContent = 'Скопировать'), 1200);
  } catch {
    // Фоллбэк
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    copyBtn.textContent = 'Скопировано!';
    setTimeout(() => (copyBtn.textContent = 'Скопировать'), 1200);
  }
});
