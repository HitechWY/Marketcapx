// Types for public Inertia pages (blogs, etc.). Seo lives in ./home.

export interface BlogListItem {
    title: string | null;
    excerpt: string;
    image: string;
    url: string;
    day: string;
    month: string;
}

export interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

export interface BlogDetail {
    title: string | null;
    image: string;
    description: string | null;
    date: string;
}

export interface RecentBlog {
    title: string | null;
    image: string;
    url: string;
    date: string;
}

export interface ShareUrls {
    facebook: string;
    linkedin: string;
    twitter: string;
}
