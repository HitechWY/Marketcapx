// Shared types for the public homepage (F1b). Mirrors the props built by
// SiteController@index / homePageData(). Element/content fields are CMS-driven,
// so values may be null when a field is empty.

export interface Banner {
    title: string | null;
    heading: string | null;
    subheading: string | null;
    button_name: string | null;
    button_link: string | null;
    video_button_name: string | null;
    video_link: string | null;
    background_image: string | null;
}

export type SectionContent = Record<string, string | null>;
export type SectionElement = Record<string, string | null>;

export interface HomeSection {
    key: string;
    content: SectionContent;
    elements: SectionElement[];
}

export interface HomeData {
    banner: Banner | null;
    sections: HomeSection[];
}

export interface Seo {
    title: string;
    description: string | null;
    keywords: string | null;
    image: string | null;
}
