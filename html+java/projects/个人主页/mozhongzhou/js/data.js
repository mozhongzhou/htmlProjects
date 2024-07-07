export default {
  index: {
    title: "你好,我是",
    me: ["漠中洲", "设计爱好者", "健身爱好者"],
    subTitle:
      "Things will work out just fine.\nBut you have to put your head down and fight,fight,fight.\nNever,ever,ever give up.",
    contact: [
      {
        name: "GitHub",
        icon: "fa-github",
        link: "https://github.com/mozhongzhou",
      },
      {
        name: "Blog",
        icon: "fa-wordpress",
        link: "https://blog.mozhongzhou.cn/",
      },
      {
        name: "Email",
        icon: "fa-envelope",
        link: "mailto:mozhongzhou@gmail.com",
      },
      {
        name: "Stack Overflow",
        icon: "fa-stack-overflow",
        link: "https://stackoverflow.com/users/22608943/mozhongzhou",
      },
    ],
    loadMore: {
      text: "了解更多",
      class: "element2",
    },
  },
  about: {
    title: "关于我",
    myself: {
      img: "../images/aboutMe.JPG",
      content:
        " <span></span><b>严奕凡</b><br><span></span><b>就读于</b>重庆大学大数据与软件学院<br><span></span><b>专业是</b>数据科学与大数据技术<br><span></span><b>热衷于</b>长跑、健身、设计<br><span></span><b>Skills:</b>",
    },
    ability: [
      {
        icon: "https://skillicons.dev/icons?i=github,stackoverflow,git,vscode,pycharm,qt,cpp,py,md,latex,docker,nginx,wordpress,html,css,vue,ubuntu,matlab,mysql,pr,unity,unreal,&theme=dark&perline=8",
      },
    ],
    loadMore: {
      text: "继续浏览",
      class: "element3",
    },
  },
  project: {
    title: "我的项目",
    list: [
      {
        name: "漠中洲的博客小站",
        text: "个人博客",
        nb: ["WordPress", "php", "nas", "MariaDB", "SEO"],
        url: "https://blog.mozhongzhou.cn/",
        img: "../images/projectBlog.png",
        content: `
                <h2>项目类型</h2>
                <p>博客</p>
                <h2>开发周期</h2>
                <p>1人/2天</p>
                <h2>开发工具</h2>
                <p>Visual Stutio Code,WordPress</p>
                <h2>项目背景</h2>
                <p>24年6-7月份实训</p>
                <h2>项目技术</h2>
                <p>WordPress:使用WordPress作为内容管理系统,快速搭建博客平台。</p>
                <p>主题定制:根据博客需求,选择并定制WordPress主题,以符合个人风格。</p>
                <p>插件使用:利用WordPress插件实现额外功能,如SEO优化、社交媒体集成等。</p>
                <p>响应式设计:确保博客在各种设备上均能良好显示。</p>
                <h2>项目预览</h2>
                <ul>
                    <li><img src="./images/projectBlog1.png"></li>
                    <li><img src="./images/projectBlog2.png"></li>
                    <li><img src="./images/projectBlog3.png"></li>
                </ul>
                `,
      },
      {
        name: "开发中...",
        text: "敬请期待！",
        nb: [""],
        url: "https://github.com/mozhongzhou",
        img: "../images/developing.png",
        content: ``,
      },
    ],
    listLoadMore: "查看",
    loadMore: {
      text: "继续浏览",
      class: "element4",
    },
  },
  contact: {
    title: "联系我",
    list: [
      {
        name: "博客",
        context: "blog.mozhongzhou.cn",
      },
      {
        name: "邮箱",
        context: "mozhongzhou@gamil.com",
      },
      {
        name: "QQ",
        context: "2014160588",
      },
      {
        name: "微信",
        context: "Yan2014160588",
      },
    ],
  },
};
