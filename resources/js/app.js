document.querySelectorAll('[data-carousel]').forEach((carousel) => {
	const track = carousel.querySelector('[data-carousel-track]');
	if (!track) return;
	const slides = Array.from(track.children);
	const next = carousel.querySelector('[data-carousel-next]');
	const previous = carousel.querySelector('[data-carousel-prev]');
	const dots = carousel.querySelector('[data-carousel-dots]');
	let current = 0;
	let timer;

	const visibleSlides = () => window.innerWidth >= 1024 ? 3 : window.innerWidth >= 768 ? 2 : 1;
	const maxIndex = () => Math.max(0, slides.length - visibleSlides());

	const render = () => {
		current = Math.min(current, maxIndex());
		track.style.transform = `translateX(-${current * (100 / visibleSlides())}%)`;
		dots?.querySelectorAll('button').forEach((dot, index) => {
			dot.classList.toggle('bg-[#e76f51]', index === current);
			dot.classList.toggle('bg-[#cbd9d8]', index !== current);
			dot.setAttribute('aria-current', index === current ? 'true' : 'false');
		});
	};

	const move = (direction) => {
		current = current + direction;
		if (current > maxIndex()) current = 0;
		if (current < 0) current = maxIndex();
		render();
	};

	for (let index = 0; index <= maxIndex(); index += 1) {
		if (!dots) break;
		const dot = document.createElement('button');
		dot.type = 'button';
		dot.className = 'h-2 w-2 rounded-full transition';
		dot.setAttribute('aria-label', `Show agency group ${index + 1}`);
		dot.addEventListener('click', () => { current = index; render(); });
		dots.append(dot);
	}

	const start = () => { timer = window.setInterval(() => move(1), 5000); };
	const stop = () => window.clearInterval(timer);

	next?.addEventListener('click', () => move(1));
	previous?.addEventListener('click', () => move(-1));
	carousel.addEventListener('mouseenter', stop);
	carousel.addEventListener('mouseleave', start);
	carousel.addEventListener('focusin', stop);
	carousel.addEventListener('focusout', start);
	window.addEventListener('resize', render);
	render();
	start();
});

const getCurrentLocale = () => {
	const searchLocale = new URLSearchParams(window.location.search).get('lang');
	const htmlLocale = (document.documentElement.lang || '').split('-')[0];
	if (searchLocale && ['en', 'fr'].includes(searchLocale)) return searchLocale;
	if (htmlLocale && ['en', 'fr'].includes(htmlLocale)) return htmlLocale;
	return 'en';
};

const preserveLocaleOnUrl = (urlValue) => {
	const locale = getCurrentLocale();
	if (!['en', 'fr'].includes(locale)) return urlValue;
	const nextUrl = new URL(urlValue, window.location.href);
	if (nextUrl.origin === window.location.origin) {
		nextUrl.searchParams.set('lang', locale);
	}
	return nextUrl.toString();
};

const applyLocaleToLinks = () => {
	document.querySelectorAll('a[href]').forEach((link) => {
		if (link.closest('[data-lang-button]')) return;
		const href = link.getAttribute('href');
		if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
		const absoluteHref = new URL(href, window.location.href);
		if (absoluteHref.origin === window.location.origin) {
			link.href = preserveLocaleOnUrl(link.href);
		}
	});
};

applyLocaleToLinks();
document.addEventListener('DOMContentLoaded', applyLocaleToLinks);

const scrollToTopButtons = document.querySelectorAll('[data-scroll-to-top]');
if (scrollToTopButtons.length > 0) {
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
	let scrollVisibilityScheduled = false;

	const updateScrollToTopButtons = () => {
		const isVisible = window.scrollY > 320;

		scrollToTopButtons.forEach((button) => {
			button.classList.toggle('pointer-events-none', !isVisible);
			button.classList.toggle('translate-y-3', !isVisible);
			button.classList.toggle('opacity-0', !isVisible);
			button.classList.toggle('visible', isVisible);
			button.classList.toggle('invisible', !isVisible);
			button.setAttribute('aria-hidden', String(!isVisible));
			button.tabIndex = isVisible ? 0 : -1;
		});
	};

	window.addEventListener('scroll', () => {
		if (scrollVisibilityScheduled) return;
		scrollVisibilityScheduled = true;
		window.requestAnimationFrame(() => {
			updateScrollToTopButtons();
			scrollVisibilityScheduled = false;
		});
	}, { passive: true });

	scrollToTopButtons.forEach((button) => {
		button.addEventListener('click', () => {
			window.scrollTo({
				top: 0,
				behavior: reducedMotion.matches ? 'auto' : 'smooth',
			});
		});
	});

	updateScrollToTopButtons();
}

window.addEventListener('pageshow', (event) => {
	if (!event.persisted) return;

	if (document.querySelector('[data-account-shell], [data-agency-shell]')) {
		window.location.reload();
	}
});

const previewObjectUrls = new WeakMap();
document.addEventListener('change', (event) => {
	const input = event.target.closest('[data-image-preview-input]');
	const file = input?.files?.[0];
	if (!input || !file || !file.type.startsWith('image/')) return;
	const preview = document.querySelector(input.dataset.imagePreviewInput);
	if (!preview) return;
	const previousUrl = previewObjectUrls.get(preview);
	if (previousUrl) URL.revokeObjectURL(previousUrl);
	const objectUrl = URL.createObjectURL(file);
	previewObjectUrls.set(preview, objectUrl);
	preview.src = objectUrl;
	preview.classList.remove('hidden');
	const fallback = document.getElementById(`${preview.id}-fallback`);
	fallback?.classList.add('hidden');
	const removeInput = input.name === 'logo' ? document.querySelector('[name="remove_logo"]') : (input.name === 'cover_image' ? document.querySelector('[name="remove_cover_image"]') : null);
	if (removeInput) removeInput.checked = false;
});

document.addEventListener('change', (event) => {
	const removeInput = event.target.closest('[name="remove_logo"], [name="remove_cover_image"]');
	if (!removeInput) return;
	const targetId = removeInput.name === 'remove_logo' ? 'agency-logo-preview' : 'agency-cover-preview';
	const preview = document.getElementById(targetId);
	const fallback = document.getElementById(`${targetId}-fallback`);
	if (removeInput.checked) {
		preview?.classList.add('hidden');
		fallback?.classList.remove('hidden');
	} else if (preview?.dataset.originalSrc) {
		preview.src = preview.dataset.originalSrc;
		preview.classList.remove('hidden');
		fallback?.classList.add('hidden');
	}
});

document.addEventListener('click', (event) => {
	const link = event.target.closest('a[href]');
	if (!link || link.closest('[data-lang-button]')) return;
	if (link.getAttribute('href') === '#saved' || link.getAttribute('href') === '#activity') {
		event.preventDefault();
		window.location.href = `/account/${link.getAttribute('href').slice(1)}?lang=${getCurrentLocale()}`;
		return;
	}
	const href = link.getAttribute('href');
	if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
	const absoluteHref = new URL(href, window.location.href);
	if (absoluteHref.origin === window.location.origin) {
		link.href = preserveLocaleOnUrl(link.href);
	}
});

document.querySelectorAll('[data-lang-button]').forEach((button) => {
	button.addEventListener('click', (event) => {
		event.stopPropagation();
	});
});

document.querySelectorAll('form').forEach((form) => {
	form.addEventListener('submit', () => {
		const locale = getCurrentLocale();
		if (!['en', 'fr'].includes(locale) || locale === 'en') return;
		if (form.querySelector('input[name="lang"]')) return;
		const hiddenLang = document.createElement('input');
		hiddenLang.type = 'hidden';
		hiddenLang.name = 'lang';
		hiddenLang.value = locale;
		form.appendChild(hiddenLang);
	});
});

document.querySelectorAll('[data-menu-toggle]').forEach((toggle) => {
	const menu = document.getElementById(toggle.getAttribute('aria-controls'));
	const backdrop = document.querySelector('[data-mobile-backdrop]');
	const close = menu.querySelector('[data-menu-close]');
	const icon = toggle.querySelector('[data-menu-icon]');

	const setOpen = (isOpen) => {
		toggle.setAttribute('aria-expanded', String(isOpen));
		menu.classList.toggle('hidden', !isOpen);
		menu.classList.toggle('translate-x-full', !isOpen);
		menu.classList.toggle('translate-x-0', isOpen);
		backdrop.classList.toggle('hidden', !isOpen);
		backdrop.classList.toggle('opacity-0', !isOpen);
		backdrop.classList.toggle('opacity-100', isOpen);
		icon.innerHTML = isOpen
			? '<path d="m6 6 12 12M18 6 6 18"/> '
			: '<path d="M4 6h16M4 12h16M4 18h16"/>';
		document.body.classList.toggle('overflow-hidden', isOpen);
	};

	toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
	close.addEventListener('click', () => setOpen(false));
	backdrop.addEventListener('click', () => setOpen(false));
	menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
});

document.querySelectorAll('[data-loading-submit]').forEach((button) => {
	button.closest('form').addEventListener('submit', () => {
		button.disabled = true;
		button.classList.add('cursor-wait', 'opacity-80');
		button.querySelector('[data-submit-label]').textContent = 'Loading...';
		button.querySelector('[data-submit-spinner]').classList.remove('hidden');
	});
});

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
	const password = document.getElementById('password');
	const icon = toggle.querySelector('[data-password-icon]');

	toggle.addEventListener('click', () => {
		const isPassword = password.type === 'password';
		password.type = isPassword ? 'text' : 'password';
		toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
		icon.innerHTML = isPassword
			? '<path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.3A10.8 10.8 0 0 1 12 5c6 0 9.5 7 9.5 7a16.8 16.8 0 0 1-3 3.8M6.2 6.2C3.8 8.1 2.5 12 2.5 12s3.5 7 9.5 7c1.2 0 2.3-.3 3.3-.7"/>'
			: '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>';
	});
});

document.querySelectorAll('[data-favorite-toggle]').forEach((toggle) => {
	toggle.addEventListener('click', async (event) => {
		event.preventDefault();
		if (toggle.dataset.authenticated !== 'true') {
			window.location.href = toggle.dataset.loginUrl;
			return;
		}
		if (toggle.disabled) return;
		toggle.disabled = true;
		try {
			const response = await fetch(`/agencies/${toggle.dataset.agencySlug}/favorite`, {
				method: 'POST',
				headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' },
			});
			if (!response.ok) throw new Error('Favorite request failed');
			const { saved } = await response.json();
			toggle.setAttribute('aria-pressed', String(saved));
			if (toggle.dataset.removeSavedAgency === 'true' && !saved) {
				window.location.reload();
				return;
			}
			toggle.classList.toggle('text-[#e76f51]', saved);
			const icon = toggle.querySelector('[data-favorite-icon]') || toggle;
			const label = toggle.querySelector('[data-favorite-label]');
			if (icon) icon.textContent = saved ? '♥' : '♡';
			if (label) label.textContent = saved ? 'Agency saved' : 'Save agency';
		} catch (error) {
			console.error(error);
		} finally {
			toggle.disabled = false;
		}
	});
});

document.querySelectorAll('[data-rating]').forEach((ratingButton) => {
	ratingButton.addEventListener('click', () => {
		if (!document.querySelector('[data-rating-section]') && !document.body.classList.contains('authenticated')) {
			window.location.href = `/login?lang=${getCurrentLocale()}`;
			return;
		}
		const rating = Number(ratingButton.dataset.rating);
		ratingButton.parentElement.querySelectorAll('[data-rating]').forEach((button) => {
			button.classList.toggle('text-[#e76f51]', Number(button.dataset.rating) <= rating);
		});
		const message = ratingButton.closest('section').querySelector('[data-rating-message]');
		message.textContent = `${rating} star${rating === 1 ? '' : 's'} selected. Sign in to publish your rating and comment.`;
	});
});

document.querySelectorAll('[data-destination-carousel]').forEach((carousel) => {
	const track = carousel.querySelector('[data-destination-track]');
	const slides = Array.from(track.children);
	const next = carousel.querySelector('[data-destination-next]');
	const previous = carousel.querySelector('[data-destination-prev]');
	let current = 0;
	let timer;

	const visibleSlides = () => window.innerWidth >= 1024 ? 3 : window.innerWidth >= 640 ? 2 : 1;
	const maxIndex = () => Math.max(0, slides.length - visibleSlides());
	const render = () => {
		current = Math.min(current, maxIndex());
		track.style.transform = `translateX(-${current * (100 / visibleSlides())}%)`;
	};
	const move = (direction) => {
		current = current + direction;
		if (current > maxIndex()) current = 0;
		if (current < 0) current = maxIndex();
		render();
	};
	const start = () => { timer = window.setInterval(() => move(1), 4500); };
	const stop = () => window.clearInterval(timer);

	next?.addEventListener('click', () => move(1));
	previous?.addEventListener('click', () => move(-1));
	carousel.addEventListener('mouseenter', stop);
	carousel.addEventListener('mouseleave', start);
	carousel.addEventListener('focusin', stop);
	carousel.addEventListener('focusout', start);
	window.addEventListener('resize', render);
	render();
	start();
});

document.querySelectorAll('[data-admin-shell]').forEach((shell) => {
	const sidebar = shell.querySelector('#admin-sidebar');
	const sidebarToggle = shell.querySelector('[data-admin-sidebar-toggle]');
	const sidebarClose = shell.querySelector('[data-admin-sidebar-close]');
	const backdrop = shell.querySelector('[data-admin-backdrop]');
	const themeToggle = shell.querySelector('[data-admin-theme-toggle]');
	const localeButtons = shell.querySelectorAll('[data-admin-locale]');
	const searchInput = shell.querySelector('[data-admin-search]');
	const searchResults = shell.querySelector('[data-admin-search-results]');
	const searchForm = shell.querySelector('[data-admin-search-form]');
	const searchItems = Array.from(shell.querySelectorAll('[data-admin-search-index] a'))
		.map((link) => ({ label: link.textContent.trim(), url: link.href }));
	const themeKey = 'travelconnect-dashboard-theme';
	const localeValues = ['en', 'fr'];

	const closeSidebar = (restoreFocus = false) => {
		if (!sidebar || window.innerWidth >= 1024) return;
		sidebar.classList.add('hidden');
		backdrop?.classList.add('hidden');
		sidebarToggle?.setAttribute('aria-expanded', 'false');
		document.body.classList.remove('overflow-hidden');
		if (restoreFocus) sidebarToggle?.focus();
	};

	sidebarToggle?.addEventListener('click', () => {
		const isOpen = sidebarToggle.getAttribute('aria-expanded') === 'true';
		if (isOpen) {
			closeSidebar();
			return;
		}
		sidebar?.classList.remove('hidden');
		backdrop?.classList.remove('hidden');
		sidebarToggle.setAttribute('aria-expanded', 'true');
		document.body.classList.add('overflow-hidden');
		sidebarClose?.focus();
	});
	sidebarClose?.addEventListener('click', () => closeSidebar(true));
	backdrop?.addEventListener('click', () => closeSidebar(true));
	sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => closeSidebar()));

	const applyTheme = (theme) => {
		const isDark = theme === 'dark';
		shell.dataset.adminTheme = isDark ? 'dark' : 'light';
		themeToggle?.setAttribute('aria-pressed', String(isDark));
		shell.querySelector('[data-theme-light-icon]')?.classList.toggle('hidden', isDark);
		shell.querySelector('[data-theme-dark-icon]')?.classList.toggle('hidden', !isDark);
	};

	applyTheme(localStorage.getItem(themeKey) === 'dark' ? 'dark' : 'light');
	themeToggle?.addEventListener('click', () => {
		const theme = shell.dataset.adminTheme === 'dark' ? 'light' : 'dark';
		localStorage.setItem(themeKey, theme);
		applyTheme(theme);
	});

	localeButtons.forEach((button) => {
		button.addEventListener('click', () => {
			if (!localeValues.includes(button.dataset.adminLocale)) return;
			const nextUrl = new URL(window.location.href);
			nextUrl.searchParams.set('lang', button.dataset.adminLocale);
			window.location.assign(nextUrl.toString());
		});
	});

	const closePopovers = (except = null) => {
		shell.querySelectorAll('[data-admin-popover-toggle]').forEach((toggle) => {
			if (toggle === except) return;
			const popover = shell.querySelector(`#${CSS.escape(toggle.dataset.adminPopoverToggle)}`);
			popover?.classList.add('hidden');
			toggle.setAttribute('aria-expanded', 'false');
		});
	};

	shell.querySelectorAll('[data-admin-popover-toggle]').forEach((toggle) => {
		toggle.addEventListener('click', () => {
			const popover = shell.querySelector(`#${CSS.escape(toggle.dataset.adminPopoverToggle)}`);
			const isOpen = toggle.getAttribute('aria-expanded') === 'true';
			closePopovers(toggle);
			popover?.classList.toggle('hidden', isOpen);
			toggle.setAttribute('aria-expanded', String(!isOpen));
		});
	});

	const closeSearch = () => {
		searchResults?.classList.add('hidden');
		searchInput?.setAttribute('aria-expanded', 'false');
	};

	const renderSearchResults = () => {
		if (!searchInput || !searchResults) return;
		const query = searchInput.value.trim().toLocaleLowerCase();
		searchResults.replaceChildren();
		if (!query) {
			closeSearch();
			return;
		}

		const matches = searchItems
			.filter((item) => item.label.toLocaleLowerCase().includes(query))
			.slice(0, 8);

		if (matches.length === 0) {
			const empty = document.createElement('p');
			empty.className = 'px-3 py-2 text-sm admin-muted';
			empty.textContent = shell.dataset.searchEmpty;
			searchResults.append(empty);
		} else {
			matches.forEach((item) => {
				const link = document.createElement('a');
				link.href = item.url;
				link.className = 'admin-nav-link block rounded-lg px-3 py-2 text-sm font-medium';
				link.textContent = item.label;
				searchResults.append(link);
			});
		}

		searchResults.classList.remove('hidden');
		searchInput.setAttribute('aria-expanded', 'true');
	};

	searchInput?.addEventListener('input', renderSearchResults);
	searchInput?.addEventListener('focus', renderSearchResults);
	searchForm?.addEventListener('submit', (event) => event.preventDefault());
	document.addEventListener('click', (event) => {
		if (!event.target.closest('[data-admin-search-form]')) closeSearch();
		if (!event.target.closest('[data-admin-popover-toggle], [data-admin-popover]')) closePopovers();
	});
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') return;
		closeSearch();
		closePopovers();
		if (sidebarToggle?.getAttribute('aria-expanded') === 'true') closeSidebar(true);
	});
});
